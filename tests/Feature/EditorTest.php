<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Tests\Fixtures\User;
use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;

class EditorTest extends TestCase
{
    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->editor = $this->editor();
        $this->actingAs($this->editor);
    }

    public function test_the_overview_shows_each_locales_progress(): void
    {
        Translations::set('fr', 'Sign out', 'Se déconnecter');
        // Flags inline base64 that could match the numbers below; they have their own tests.
        config(['translations.flags' => false]);

        $this->get('/translations')
            ->assertOk()
            ->assertSeeInOrder(['English', 'Source', '100', 'German', '10', '9 missing', 'French', '70', '3 missing', '1 not exported'], false)
            ->assertSee('1 change is not in your lang files yet')
            ->assertSee('Add a language');
    }

    public function test_the_editor_lists_source_and_translation(): void
    {
        $this->get('/translations/fr')
            ->assertOk()
            ->assertSee('French')
            ->assertSee('Cancel subscription')
            ->assertSee('Résilier l&#039;abonnement', false)
            ->assertSee('Missing <span>4</span>', false)
            ->assertSee('invoice.paid')
            ->assertSee('name="_method" value="PUT"', false);
    }

    public function test_filters_search_and_files(): void
    {
        $this->get('/translations/fr?filter=missing')->assertOk()
            ->assertSee('invoice.paid')->assertDontSee('invoice.due');

        $this->get('/translations/fr?q=r%C3%A9silier')->assertOk()
            ->assertSee('Résilier')->assertDontSee('invoice.title');

        $this->get('/translations/fr?file=auth')->assertOk()
            ->assertSee('failed')->assertDontSee('invoice.title');

        $this->get('/translations/fr?filter=changed')->assertOk()->assertSee('Nothing to export.');
        $this->get('/translations/fr?q=zzzz')->assertOk()->assertSee('Nothing matches.');
        // An unknown file or filter falls back to everything.
        $this->get('/translations/fr?file=nope&filter=nope')->assertOk()->assertSee('invoice.title');
    }

    public function test_pagination(): void
    {
        config()->set('translations.per_page', 3);

        $this->get('/translations/fr')->assertOk()->assertSee('Showing 1–3 of 10')->assertSee('page=2', false);
        $this->get('/translations/fr?page=4')->assertOk()->assertSee('Showing 10–10 of 10');
    }

    public function test_saving_without_javascript_redirects_back_to_the_row(): void
    {
        $this->from('/translations/fr?filter=missing')
            ->put('/translations/fr/entries', ['namespace' => '*', 'group' => 'billing', 'key' => 'invoice.paid', 'value' => 'Payée'])
            ->assertRedirect('/translations/fr?filter=missing#'.Translations::entry('fr', 'billing.invoice.paid')->id())
            ->assertSessionHas('translations.status', 'Saved “billing.invoice.paid”.');

        $this->assertSame('Payée', Translations::get('fr', 'billing.invoice.paid'));
    }

    public function test_saving_with_javascript_answers_json(): void
    {
        $this->putJson('/translations/fr/entries', ['namespace' => '*', 'group' => 'billing', 'key' => 'invoice.title', 'value' => 'Facture'])
            ->assertOk()
            ->assertJsonPath('entry.value', 'Facture')
            ->assertJsonPath('entry.status', 'changed')
            ->assertJsonPath('entry.pending', true)
            ->assertJsonPath('entry.warnings', ['Missing :number, which the source text uses.'])
            ->assertJsonPath('message', 'Saved.');

        $this->assertSame((string) $this->editor->id, Override::query()->value('updated_by'));
    }

    public function test_json_keys_and_keys_as_written(): void
    {
        $this->putJson('/translations/fr/entries', ['group' => '*', 'key' => 'Sign out', 'value' => 'Se déconnecter'])->assertOk();
        $this->putJson('/translations/fr/entries', ['key' => 'billing.cancel', 'value' => 'Arrêter'])->assertOk();

        $this->assertSame('Se déconnecter', __('Sign out', [], 'fr'));
        $this->assertSame('Arrêter', __('billing.cancel', [], 'fr'));
    }

    public function test_the_warning_shows_after_a_plain_form_save(): void
    {
        $this->from('/translations/fr')
            ->put('/translations/fr/entries', ['group' => 'billing', 'key' => 'invoice.title', 'value' => 'Facture'])
            ->assertSessionHas('translations.status', 'Saved “billing.invoice.title”. Check it: Missing :number, which the source text uses.');

        $this->get('/translations/fr?filter=warnings')->assertOk()
            ->assertSee('Missing :number, which the source text uses.')
            ->assertSee('Needs a look <span>1</span>', false);
    }

    public function test_validation(): void
    {
        $this->putJson('/translations/fr/entries', ['value' => 'x'])->assertJsonValidationErrors('key');
        $this->putJson('/translations/fr/entries', ['key' => 'billing.cancel'])->assertJsonValidationErrors('value');
        $this->putJson('/translations/fr/entries', ['group' => '../../etc', 'key' => 'passwd', 'value' => 'x'])->assertJsonValidationErrors('key');
        $this->putJson('/translations/fr/entries', ['key' => 'billing.cancel', 'value' => str_repeat('a', 20001)])->assertJsonValidationErrors('value');

        $this->assertSame(0, Override::query()->count());
    }

    public function test_revert(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');

        $this->deleteJson('/translations/fr/entries', ['group' => 'billing', 'key' => 'cancel'])
            ->assertOk()
            ->assertJsonPath('entry.value', "Résilier l'abonnement")
            ->assertJsonPath('entry.status', 'translated');

        Translations::set('fr', 'billing.cancel', 'Arrêter');
        $this->from('/translations/changes')
            ->delete('/translations/fr/entries', ['group' => 'billing', 'key' => 'cancel'])
            ->assertRedirectContains('/translations/changes#')
            ->assertSessionHas('translations.status', 'Reverted “billing.cancel” to the lang file.');

        $this->assertSame(0, Override::query()->count());
    }

    public function test_the_pending_changes_review(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter', $this->editor);
        Translations::set('de', 'Pay now', 'Jetzt zahlen', $this->editor);

        $this->get('/translations/changes')
            ->assertOk()
            ->assertSee('2 changes to export')
            ->assertSee('php artisan translations:export --dry-run')
            ->assertSeeInOrder(['German', 'Pay now', 'Not there yet', 'Jetzt zahlen', 'French', 'cancel', 'Résilier l&#039;abonnement', 'Arrêter'], false)
            ->assertSee('Maya Okafor')
            ->assertSee('<span class="trans-avatar" aria-hidden="true">MO</span>', false);
    }

    public function test_the_review_flags_files_that_changed_since_the_edit(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        file_put_contents($this->lang.'/fr/billing.php', "<?php\n\nreturn ['cancel' => 'Résilier'];\n");

        $this->get('/translations/changes')->assertOk()->assertSee('The lang file changed after this edit.');
    }

    public function test_no_pending_changes(): void
    {
        $this->get('/translations/changes')->assertOk()->assertSee('No pending changes.');
    }

    public function test_add_a_locale(): void
    {
        $this->post('/translations/locales', ['code' => 'nl'])
            ->assertRedirect('/translations/nl')
            ->assertSessionHas('translations.status');

        $this->get('/translations/nl')->assertOk()->assertSee('Dutch')->assertSee('Missing <span>10</span>', false);
    }

    public function test_add_locale_errors(): void
    {
        $this->from('/translations')->post('/translations/locales', ['code' => 'fr'])
            ->assertRedirect('/translations')->assertSessionHasErrors(['code' => 'The “fr” locale already exists.']);
        $this->from('/translations')->post('/translations/locales', ['code' => 'French'])
            ->assertSessionHasErrors('code');
        $this->from('/translations')->post('/translations/locales', [])->assertSessionHasErrors('code');
    }

    public function test_the_locale_switcher_works_without_javascript(): void
    {
        $this->get('/translations?locale=de')->assertRedirect('/translations/de');
        $this->get('/translations?locale=xx')->assertOk();
    }

    public function test_the_source_locale_has_no_source_column(): void
    {
        $this->get('/translations/en')->assertOk()->assertDontSee('<col class="trans-col-source">', false);
    }

    public function test_right_to_left_locales(): void
    {
        Translations::addLocale('ar');

        $this->get('/translations/ar')->assertOk()->assertSee('dir="rtl"', false);
    }

    public function test_the_editor_can_use_the_apps_layout(): void
    {
        config()->set('translations.layout', 'host');
        file_put_contents($this->lang.'/../host.blade.php', '<html><body><nav>Halyard</nav>@yield("content")</body></html>');
        app('view')->addLocation(dirname($this->lang));

        $this->get('/translations')->assertOk()->assertSee('<nav>Halyard</nav>', false)->assertSee('class="trans"', false);
    }

    public function test_languages_show_round_flags(): void
    {
        $this->get('/translations')->assertOk()->assertSee('class="trans-flag"', false)->assertSee('data:image/svg+xml;base64,', false);
    }
}
