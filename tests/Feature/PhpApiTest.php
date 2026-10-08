<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Ruvelo\Translations\Entry;
use Ruvelo\Translations\Events\LocaleAdded;
use Ruvelo\Translations\Events\TranslationUpdated;
use Ruvelo\Translations\Exceptions\InvalidLocale;
use Ruvelo\Translations\Exceptions\LocaleAlreadyExists;
use Ruvelo\Translations\Exceptions\SuggestionsUnavailable;
use Ruvelo\Translations\Exceptions\TranslationsException;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;

class PhpApiTest extends TestCase
{
    public function test_locales_are_detected_from_the_lang_folder(): void
    {
        // en first (the source), then alphabetical: folders, JSON files and lang/vendor.
        $this->assertSame(['en', 'de', 'fr'], Translations::locales());
        $this->assertSame('en', Translations::sourceLocale());
    }

    public function test_locales_can_be_configured(): void
    {
        config()->set('translations.locales', ['fr', 'nl']);
        Translations::catalogue()->refresh();

        $this->assertSame(['en', 'fr', 'nl'], Translations::locales());
    }

    public function test_the_source_locale_can_be_configured(): void
    {
        config()->set('translations.source_locale', 'fr');

        $this->assertSame(['fr', 'de', 'en'], Translations::locales());
        $this->assertSame('Facture :number', Translations::entry('de', 'billing.invoice.title')->source);
    }

    public function test_get_set_and_forget(): void
    {
        $this->assertNull(Translations::get('fr', 'billing.cancel'));
        $this->assertSame("Résilier l'abonnement", Translations::value('fr', 'billing.cancel'));

        $entry = Translations::set('fr', 'billing.cancel', 'Arrêter');

        $this->assertInstanceOf(Entry::class, $entry);
        $this->assertSame('Arrêter', $entry->value());
        $this->assertSame("Résilier l'abonnement", $entry->file);
        $this->assertSame('Cancel subscription', $entry->source);
        $this->assertSame(Entry::CHANGED, $entry->status());
        $this->assertSame('Arrêter', Translations::get('fr', 'billing.cancel'));

        $this->assertTrue(Translations::forget('fr', 'billing.cancel'));
        $this->assertFalse(Translations::forget('fr', 'billing.cancel'));
        $this->assertNull(Translations::get('fr', 'billing.cancel'));
    }

    public function test_saving_the_files_own_text_removes_the_override(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        Translations::set('fr', 'billing.cancel', "Résilier l'abonnement");

        $this->assertSame(0, Override::query()->count());
    }

    public function test_saving_an_empty_missing_string_stores_nothing(): void
    {
        Translations::set('fr', 'billing.invoice.paid', '');

        $this->assertSame(0, Override::query()->count());
        $this->assertTrue(Translations::entry('fr', 'billing.invoice.paid')->isMissing());
    }

    public function test_events_for_every_change(): void
    {
        Event::fake([TranslationUpdated::class]);
        $editor = $this->editor();

        Translations::set('fr', 'billing.cancel', 'Arrêter', $editor);
        Translations::set('fr', 'billing.cancel', 'Arrêter', $editor); // unchanged: no event
        Translations::forget('fr', 'billing.cancel', $editor);

        Event::assertDispatchedTimes(TranslationUpdated::class, 2);
        Event::assertDispatched(TranslationUpdated::class, fn (TranslationUpdated $e) => ! $e->wasReverted()
            && $e->locale === 'fr' && $e->key->full() === 'billing.cancel'
            && $e->previous === "Résilier l'abonnement" && $e->value === 'Arrêter' && $e->user?->is($editor));
        Event::assertDispatched(TranslationUpdated::class, fn (TranslationUpdated $e) => $e->wasReverted()
            && $e->previous === 'Arrêter' && $e->value === "Résilier l'abonnement");
    }

    public function test_the_editor_is_recorded(): void
    {
        $editor = $this->editor();

        Translations::set('fr', 'billing.cancel', 'Arrêter', $editor);

        $this->assertSame((string) $editor->id, Override::query()->value('updated_by'));
        $this->assertSame('Maya Okafor', Override::query()->firstOrFail()->editorName());
    }

    public function test_keys_resolve_like_laravel_does(): void
    {
        $this->assertTrue(Translations::key('Pay now')->isJson());
        $this->assertTrue(Translations::key('Welcome back, :name!')->isJson());
        $this->assertSame('billing', Translations::key('billing.cancel')->group);
        $this->assertSame('courier', Translations::key('courier::messages.sent')->namespace);
        // A dotted sentence with no such file is a JSON key.
        $this->assertTrue(Translations::key('Done. Thanks.')->isJson());
        $this->assertTrue(Translations::key('nofile.key')->isJson());
    }

    public function test_entries_and_filters(): void
    {
        $all = Translations::entries('fr');
        $keys = $all->map(fn (Entry $entry) => $entry->key->full())->all();

        // JSON first, then files; the source's keys appear even when missing.
        $this->assertSame('Pay now', $keys[0]);
        $this->assertContains('billing.invoice.paid', $keys);
        $this->assertContains('auth.failed', $keys);

        $this->assertSame(['Sign out', 'auth.failed', 'billing.invoice.paid', 'billing.plans'], Translations::missing('fr')->map(fn (Entry $e) => $e->key->full())->all());
        $this->assertCount(5, Translations::entries('fr', 'all', '', 'billing'));
        $this->assertCount(1, Translations::entries('fr', 'all', 'résilier'));
        $this->assertCount(1, Translations::entries('fr', 'all', 'invoice.due'));

        Translations::set('fr', 'billing.invoice.paid', 'Payée :amount');
        $this->assertCount(1, Translations::entries('fr', 'changed'));
        $this->assertCount(1, Translations::entries('fr', 'warnings'));
        $this->assertSame(['Adds :amount, which the source text doesn\'t have.'], Translations::entry('fr', 'billing.invoice.paid')->warnings());
    }

    public function test_progress(): void
    {
        $progress = Translations::progress('fr');

        // Source: 3 JSON + 1 auth + 5 billing + 1 courier = 10 strings; French has 6.
        $this->assertSame(10, $progress->total);
        $this->assertSame(6, $progress->translated);
        $this->assertSame(4, $progress->missing());
        $this->assertSame(60, $progress->percent());
        $this->assertFalse($progress->isComplete());

        Translations::set('fr', 'Sign out', 'Se déconnecter');
        $this->assertSame(7, Translations::progress('fr')->translated);
        $this->assertSame(1, Translations::progress('fr')->pending);

        $this->assertSame(100, Translations::progress('en')->percent());
        $this->assertTrue(Translations::progress('en')->isSource);
        $this->assertSame(['locale' => 'de', 'total' => 10, 'translated' => 1, 'missing' => 9, 'percent' => 10, 'pending' => 0, 'source' => false], Translations::progress('de')->toArray());
    }

    public function test_add_locale(): void
    {
        Event::fake([LocaleAdded::class]);

        $this->assertSame('pt_BR', Translations::addLocale(' pt_BR '));

        $this->assertContains('pt_BR', Translations::locales());
        $this->assertSame(0, Translations::progress('pt_BR')->percent());
        Event::assertDispatched(LocaleAdded::class, fn (LocaleAdded $e) => $e->locale === 'pt_BR');
    }

    public function test_add_locale_refuses_bad_or_existing_codes(): void
    {
        foreach (['', 'french', '../x', 'f r', 'EN'] as $code) {
            try {
                Translations::addLocale($code);
                $this->fail("Accepted “{$code}”");
            } catch (InvalidLocale $e) {
                $this->assertInstanceOf(TranslationsException::class, $e);
            }
        }

        $this->expectException(LocaleAlreadyExists::class);
        Translations::addLocale('fr');
    }

    public function test_setting_an_unknown_locale_fails(): void
    {
        $this->expectException(InvalidLocale::class);
        $this->expectExceptionMessage('Add it first');

        Translations::set('it', 'billing.cancel', 'Annulla');
    }

    public function test_pending_lists_unexported_changes_newest_first(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        $this->travel(1)->minutes();
        Translations::set('de', 'Pay now', 'Jetzt zahlen');

        $pending = Translations::pending();
        $this->assertSame(['de', 'fr'], array_map(fn (Entry $e) => $e->locale, $pending));
        $this->assertCount(1, Translations::pending('fr'));
    }

    public function test_a_file_changed_after_the_edit_is_flagged(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        $this->assertFalse(Translations::entry('fr', 'billing.cancel')->fileChangedSinceEdit());

        file_put_contents($this->lang.'/fr/billing.php', "<?php\n\nreturn ['cancel' => 'Résilier'];\n");
        Translations::catalogue()->refresh();

        $this->assertTrue(Translations::entry('fr', 'billing.cancel')->fileChangedSinceEdit());
    }

    public function test_check_and_suggestions_without_the_ai_sdk_configured(): void
    {
        $this->assertSame(['Missing :name, which the source text uses.'], Translations::check('Hi :name', 'Salut'));

        config()->set('ai.default', null);
        $this->assertFalse(Translations::canSuggest());
        $this->expectException(SuggestionsUnavailable::class);
        Translations::suggest('fr', 'billing.cancel');
    }

    public function test_the_factory(): void
    {
        $override = Override::factory()->locale('de')->key('billing.invoice.paid')->value('Bezahlt')->create();

        $this->assertSame('billing', $override->group);
        $this->assertSame('invoice.paid', $override->key);
        $this->assertSame('Bezahlt', __('billing.invoice.paid', [], 'de'));
        $this->assertTrue(Override::factory()->json('Pay now')->create()->translationKey()->isJson());
        $this->assertInstanceOf(Key::class, Override::factory()->create()->translationKey());
    }

    public function test_nobody_can_edit_until_the_gate_is_defined(): void
    {
        $user = $this->user('Tom Reyes', translator: true);

        $this->assertFalse(Translations::canEdit($user));
        $this->assertFalse(Translations::canEdit(null));

        $this->editor();
        $this->assertTrue(Translations::canEdit($user));
        $this->assertFalse(Translations::canEdit($this->user('Kenji Mori')));
    }
}
