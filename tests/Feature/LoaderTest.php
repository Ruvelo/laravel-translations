<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Loader\OverrideLoader;
use Ruvelo\Translations\Loader\RecordingTranslator;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;

class LoaderTest extends TestCase
{
    public function test_the_loader_and_translator_are_layered_on_laravels(): void
    {
        $this->assertInstanceOf(OverrideLoader::class, app('translation.loader'));
        $this->assertInstanceOf(RecordingTranslator::class, app('translator'));
        $this->assertSame('Facture 7', __('billing.invoice.title', ['number' => 7], 'fr'));
    }

    public function test_an_override_beats_the_file(): void
    {
        Translations::set('fr', 'billing.invoice.title', 'Facture n° :number');

        $this->assertSame('Facture n° 7', __('billing.invoice.title', ['number' => 7], 'fr'));
        // Its neighbours still come from the file.
        $this->assertSame('Échéance le 1 mai', __('billing.invoice.due', ['date' => '1 mai'], 'fr'));
        $this->assertSame("Résilier l'abonnement", __('billing.cancel', [], 'fr'));
    }

    public function test_an_override_can_add_a_key_the_file_lacks(): void
    {
        Translations::set('fr', 'billing.invoice.paid', 'Payée');

        $this->assertSame('Payée', __('billing.invoice.paid', [], 'fr'));
        $this->assertSame(['title' => 'Facture :number', 'due' => 'Échéance le :date', 'paid' => 'Payée'], __('billing.invoice', [], 'fr'));
    }

    public function test_json_keys(): void
    {
        $this->assertSame('Sign out', __('Sign out', [], 'fr'));

        Translations::set('fr', 'Sign out', 'Se déconnecter');
        Translations::set('fr', 'Pay now', 'Régler');

        $this->assertSame('Se déconnecter', __('Sign out', [], 'fr'));
        $this->assertSame('Régler', __('Pay now', [], 'fr'));
        $this->assertSame('Bon retour, Maya !', __('Welcome back, :name!', ['name' => 'Maya'], 'fr'));
    }

    public function test_the_fallback_locale_gets_overrides_too(): void
    {
        Translations::set('en', 'billing.invoice.paid', 'Paid in full');

        // German has no billing.invoice.paid: Laravel falls back to English.
        $this->assertSame('Paid in full', __('billing.invoice.paid', [], 'de'));
        $this->assertSame('Abo kündigen', __('billing.cancel', [], 'de'));
    }

    public function test_package_namespaces(): void
    {
        app('translator')->addNamespace('courier', __DIR__.'/../Fixtures/packages/courier/lang');

        // lang/vendor beats the package's own file, as in Laravel.
        $this->assertSame('Message sent to Maya', __('courier::messages.sent', ['name' => 'Maya']));
        $this->assertSame('Could not send the message', __('courier::messages.failed'));

        Translations::set('en', Key::group('messages', 'failed', 'courier'), 'The message bounced');
        Translations::set('fr', 'courier::messages.sent', 'Envoyé à :name');

        $this->assertSame('The message bounced', __('courier::messages.failed'));
        $this->assertSame('Envoyé à Maya', __('courier::messages.sent', ['name' => 'Maya'], 'fr'));
    }

    public function test_choice_uses_overrides(): void
    {
        Translations::set('fr', 'billing.plans', '{0} Aucune offre|{1} Une offre|[2,*] :count offres');

        $this->assertSame('3 offres', trans_choice('billing.plans', 3, [], 'fr'));
        $this->assertSame('Aucune offre', trans_choice('billing.plans', 0, [], 'fr'));
    }

    public function test_compiled_overrides_are_cached_and_invalidated(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        app('translator')->setLoaded([]);

        $this->assertSame('Arrêter', __('billing.cancel', [], 'fr'));
        app('translator')->setLoaded([]);
        DB::enableQueryLog();
        $this->assertSame('Arrêter', __('billing.cancel', [], 'fr'));
        $queries = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'translations_overrides'));
        $this->assertCount(0, $queries, 'The second load should come from the cache.');

        // Saving clears the cache and the translator's memory of the file.
        Translations::set('fr', 'billing.cancel', 'Résilier');
        $this->assertSame('Résilier', __('billing.cancel', [], 'fr'));

        Translations::forget('fr', 'billing.cancel');
        $this->assertSame("Résilier l'abonnement", __('billing.cancel', [], 'fr'));
    }

    public function test_editing_the_table_directly_needs_a_flush(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        $this->assertSame('Arrêter', __('billing.cancel', [], 'fr'));

        Override::query()->update(['value' => 'Stop']);
        app('translator')->setLoaded([]);
        $this->assertSame('Arrêter', __('billing.cancel', [], 'fr'));

        Translations::flushCache();
        $this->assertSame('Stop', __('billing.cancel', [], 'fr'));
    }

    public function test_the_cache_can_be_turned_off(): void
    {
        config()->set('translations.cache.enabled', false);
        Translations::set('fr', 'billing.cancel', 'Arrêter');

        $this->assertSame('Arrêter', __('billing.cancel', [], 'fr'));
        $this->assertFalse(Cache::has('translations:1:fr:*:billing'));
    }

    public function test_the_site_keeps_working_without_the_table(): void
    {
        Schema::drop('translations_overrides');
        app('translator')->setLoaded([]);

        $this->assertSame('Facture 3', __('billing.invoice.title', ['number' => 3], 'fr'));
    }

    public function test_unsafe_locales_are_ignored(): void
    {
        $loader = app('translation.loader');

        $this->assertSame([], $loader->load('../../etc', 'passwd', '*'));
    }
}
