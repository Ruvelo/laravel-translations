<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Ruvelo\Translations\Events\TranslationsExported;
use Ruvelo\Translations\Exceptions\ExportFailed;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;

class ExportTest extends TestCase
{
    public function test_a_round_trip_keeps_the_files_structure(): void
    {
        Translations::set('fr', 'billing.invoice.title', 'Facture n° :number');
        Translations::set('fr', 'billing.invoice.paid', 'Payée');
        Translations::set('fr', 'billing.plans', '{0} Aucune offre|{1} Une offre|[2,*] :count offres');

        $result = Translations::export();

        $this->assertFalse($result->dryRun);
        $this->assertSame(3, $result->count());
        $this->assertCount(1, $result->files);
        $this->assertSame(<<<'PHP'
            <?php

            return [
                // La page facture
                'invoice' => [
                    'title' => 'Facture n° :number',
                    'due'   => 'Échéance le :date',
                    'paid' => 'Payée',
                ],
                'plans' => '{0} Aucune offre|{1} Une offre|[2,*] :count offres',
                'cancel' => "Résilier l'abonnement",
            ];

            PHP, $this->langFile('fr/billing.php'));

        // The overrides now match the file: nothing is pending, and the app
        // shows the same text as before the export.
        $this->assertSame([], Translations::pending());
        $this->assertSame('Payée', __('billing.invoice.paid', [], 'fr'));
        $this->assertSame(3, Override::query()->count());
    }

    public function test_a_dry_run_writes_nothing_and_shows_a_diff(): void
    {
        $before = $this->langFile('fr/billing.php');
        Translations::set('fr', 'billing.cancel', 'Arrêter');

        $result = Translations::export(dryRun: true);

        $this->assertSame($before, $this->langFile('fr/billing.php'));
        $this->assertTrue($result->dryRun);
        $diff = $result->files[0]->diff();
        $this->assertStringContainsString('--- a/lang/fr/billing.php', $diff);
        $this->assertStringContainsString("-    'cancel' => \"Résilier l'abonnement\",", $diff);
        $this->assertStringContainsString("+    'cancel' => 'Arrêter',", $diff);
        $this->assertCount(1, Translations::pending());
    }

    public function test_json_files(): void
    {
        Translations::set('fr', 'Sign out', 'Se déconnecter');
        Translations::set('fr', 'Pay now', 'Régler');

        Translations::export();

        $this->assertSame(<<<'JSON'
            {
                "Pay now": "Régler",
                "Sign out": "Se déconnecter",
                "Welcome back, :name!": "Bon retour, :name !"
            }

            JSON, $this->langFile('fr.json'));
    }

    public function test_a_new_locale_gets_new_files_in_the_source_order(): void
    {
        Translations::addLocale('es');
        Translations::set('es', 'billing.cancel', 'Cancelar suscripción');
        Translations::set('es', 'billing.invoice.title', 'Factura :number');
        Translations::set('es', 'Pay now', 'Pagar ahora');

        $result = Translations::export('es');

        $this->assertCount(2, $result->files);
        $this->assertTrue($result->files[0]->isNew());
        $this->assertSame("{\n    \"Pay now\": \"Pagar ahora\"\n}\n", $this->langFile('es.json'));
        $this->assertSame(<<<'PHP'
            <?php

            return [
                'invoice' => [
                    'title' => 'Factura :number',
                ],
                'cancel' => 'Cancelar suscripción',
            ];

            PHP, $this->langFile('es/billing.php'));
    }

    public function test_package_keys_go_to_lang_vendor(): void
    {
        app('translator')->addNamespace('courier', __DIR__.'/../Fixtures/packages/courier/lang');
        Translations::set('fr', Key::group('messages', 'failed', 'courier'), 'Échec de l’envoi');

        Translations::export();

        $this->assertSame(['sent' => 'Message envoyé à :name', 'failed' => 'Échec de l’envoi'], require $this->lang.'/vendor/courier/fr/messages.php');
    }

    public function test_only_the_chosen_locales(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        Translations::set('de', 'billing.cancel', 'Kündigen');

        Translations::export(['de']);

        $this->assertStringContainsString("'cancel' => 'Kündigen'", $this->langFile('de/billing.php'));
        $this->assertStringNotContainsString('Arrêter', $this->langFile('fr/billing.php'));
        $this->assertCount(1, Translations::pending());
    }

    public function test_prune_deletes_the_overrides_the_files_now_hold(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        Translations::set('de', 'billing.cancel', 'Kündigen');

        $result = Translations::export('fr', prune: true);

        $this->assertSame(1, $result->pruned);
        $this->assertSame(1, Override::query()->count());
        $this->assertSame('Arrêter', __('billing.cancel', [], 'fr'));

        // Later, after a deploy, the German file says the same: prune it.
        file_put_contents($this->lang.'/de/billing.php', "<?php\n\nreturn ['cancel' => 'Kündigen'];\n");
        Translations::catalogue()->refresh();
        $this->assertSame(1, Translations::prune());
        $this->assertSame(0, Override::query()->count());
    }

    public function test_files_it_cannot_edit_in_place_are_rewritten(): void
    {
        file_put_contents($this->lang.'/fr/billing.php', "<?php\n\nreturn array_merge(['cancel' => 'Résilier'], ['invoice' => ['title' => 'Facture']]);\n");
        Translations::catalogue()->refresh();
        Translations::set('fr', 'billing.invoice.due', 'Échéance');

        $result = Translations::export();

        $this->assertTrue($result->files[0]->rewritten);
        $this->assertSame(['cancel' => 'Résilier', 'invoice' => ['title' => 'Facture', 'due' => 'Échéance']], require $this->lang.'/fr/billing.php');
    }

    public function test_the_event_is_fired(): void
    {
        Event::fake([TranslationsExported::class]);
        Translations::set('fr', 'billing.cancel', 'Arrêter');

        Translations::export(dryRun: true);
        Event::assertNotDispatched(TranslationsExported::class);

        Translations::export();
        Event::assertDispatched(TranslationsExported::class, fn ($event) => $event->result->count() === 1 && $event->result->locales() === ['fr']);
    }

    public function test_nothing_to_export(): void
    {
        $this->assertTrue(Translations::export()->isEmpty());
    }

    public function test_an_invalid_json_file_fails_clearly(): void
    {
        Translations::set('fr', 'Sign out', 'Se déconnecter');
        file_put_contents($this->lang.'/fr.json', '{broken');

        $this->expectException(ExportFailed::class);
        $this->expectExceptionMessage('lang/fr.json');

        Translations::export();
    }

    public function test_an_unwritable_folder_fails_clearly(): void
    {
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            $this->markTestSkipped('Root can write anywhere.');
        }

        Translations::set('fr', 'billing.cancel', 'Arrêter');
        chmod($this->lang.'/fr', 0555);

        try {
            $this->expectException(ExportFailed::class);
            Translations::export();
        } finally {
            chmod($this->lang.'/fr', 0755);
        }
    }
}
