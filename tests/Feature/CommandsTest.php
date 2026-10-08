<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ruvelo\Translations\Exceptions\ImportFailed;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;

class CommandsTest extends TestCase
{
    public function test_export(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');

        $this->artisan('translations:export', ['--dry-run' => true])
            ->expectsOutputToContain("+    'cancel' => 'Arrêter',")
            ->expectsOutputToContain('Would write 1 change to 1 file.')
            ->assertSuccessful();
        $this->assertStringNotContainsString('Arrêter', $this->langFile('fr/billing.php'));

        $this->artisan('translations:export')
            ->expectsOutputToContain('lang/fr/billing.php')
            ->expectsOutputToContain('Wrote 1 change to 1 file.')
            ->assertSuccessful();
        $this->assertStringContainsString("'cancel' => 'Arrêter',", $this->langFile('fr/billing.php'));

        $this->artisan('translations:export')->expectsOutputToContain('Nothing to export')->assertSuccessful();
    }

    public function test_export_one_locale_and_prune(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        Translations::set('de', 'billing.cancel', 'Kündigen');

        $this->artisan('translations:export', ['--locale' => ['de'], '--prune' => true])
            ->expectsOutputToContain('Deleted 1 overrides')
            ->assertSuccessful();

        $this->assertSame(1, Override::query()->count());
    }

    public function test_export_failures_are_reported(): void
    {
        Translations::set('fr', 'Sign out', 'Se déconnecter');
        file_put_contents($this->lang.'/fr.json', '{broken');

        $this->artisan('translations:export')->expectsOutputToContain('not valid JSON')->assertFailed();
    }

    public function test_prune(): void
    {
        $this->artisan('translations:prune')->expectsOutputToContain('Nothing to prune.')->assertSuccessful();

        Translations::set('fr', 'billing.cancel', 'Arrêter');
        Translations::export();

        $this->artisan('translations:prune')->expectsOutputToContain('Deleted 1 override the lang files now hold.')->assertSuccessful();
    }

    public function test_scan(): void
    {
        $code = (string) realpath(__DIR__.'/../Fixtures/code');

        $this->artisan('translations:scan', ['paths' => [$code], '--unused' => true])
            ->expectsOutputToContain('keys are missing from en')
            ->expectsOutputToContain('billing.refund')
            ->expectsOutputToContain('--create')
            ->expectsOutputToContain('look unused')
            ->expectsOutputToContain('Sign out')
            ->assertSuccessful();

        $this->assertSame(0, Override::query()->count());
    }

    public function test_scan_create(): void
    {
        $code = (string) realpath(__DIR__.'/../Fixtures/code');

        $this->artisan('translations:scan', ['paths' => [$code], '--create' => true])
            ->expectsOutputToContain('Added them to en as pending changes.')
            ->assertSuccessful();

        $this->assertSame('Refund', Translations::get('en', 'billing.refund'));
        $this->assertSame('Download receipt', Translations::get('en', 'Download receipt'));
        $this->assertSame('Seats', __('billing.seats'));

        $this->artisan('translations:scan', ['paths' => [$code]])
            ->expectsOutputToContain('Every key is in the source locale (en).')
            ->assertSuccessful();
    }

    public function test_import_a_json_file(): void
    {
        $file = dirname($this->lang).'/fr.json';
        file_put_contents($file, json_encode(['Pay now' => 'Payer maintenant', 'Sign out' => 'Se déconnecter']));

        $this->artisan('translations:import', ['path' => $file])
            ->expectsOutputToContain('Imported 1 translation (1 already matched).')
            ->assertSuccessful();

        $this->assertSame('Se déconnecter', __('Sign out', [], 'fr'));
        $this->assertCount(1, Translations::pending('fr'));
    }

    public function test_import_a_php_file_into_a_new_locale(): void
    {
        $file = dirname($this->lang).'/factuur.php';
        file_put_contents($file, "<?php return ['cancel' => 'Opzeggen', 'invoice' => ['title' => 'Factuur :number']];");

        $this->artisan('translations:import', ['path' => $file, '--locale' => 'nl', '--group' => 'billing'])->assertSuccessful();

        $this->assertContains('nl', Translations::locales());
        $this->assertSame('Factuur 9', __('billing.invoice.title', ['number' => 9], 'nl'));
    }

    public function test_import_a_lang_folder(): void
    {
        $dir = dirname($this->lang).'/agency';
        mkdir($dir.'/de', 0777, true);
        file_put_contents($dir.'/de/billing.php', "<?php return ['cancel' => 'Abonnement kündigen', 'invoice' => ['paid' => 'Bezahlt']];");
        file_put_contents($dir.'/de.json', json_encode(['Pay now' => 'Jetzt bezahlen']));

        $this->artisan('translations:import', ['path' => $dir, '--user' => (string) $this->editor()->id])
            ->expectsOutputToContain('Imported 3 translations')
            ->assertSuccessful();

        $this->assertSame('Bezahlt', __('billing.invoice.paid', [], 'de'));
        $this->assertNotNull(Override::query()->value('updated_by'));
    }

    public function test_import_from_the_translation_manager_table(): void
    {
        Schema::create('ltm_translations', function (Blueprint $table) {
            $table->id();
            $table->integer('status')->default(0);
            $table->string('locale');
            $table->string('group');
            $table->text('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
        DB::table('ltm_translations')->insert([
            ['locale' => 'fr', 'group' => 'billing', 'key' => 'invoice.paid', 'value' => 'Payée'],
            ['locale' => 'fr', 'group' => '_json', 'key' => 'Sign out', 'value' => 'Déconnexion'],
            ['locale' => 'fr', 'group' => 'vendor/courier/messages', 'key' => 'failed', 'value' => 'Échec'],
            ['locale' => 'fr', 'group' => 'billing', 'key' => 'cancel', 'value' => "Résilier l'abonnement"],
            ['locale' => 'fr', 'group' => 'billing', 'key' => 'refund', 'value' => null],
        ]);

        $this->artisan('translations:import', ['--translation-manager' => true])
            ->expectsOutputToContain('Imported 3 translations (1 already matched).')
            ->assertSuccessful();

        $this->assertSame('Payée', Translations::get('fr', 'billing.invoice.paid'));
        $this->assertSame('Déconnexion', Translations::get('fr', 'Sign out'));
        $this->assertSame('Échec', Translations::get('fr', 'courier::messages.failed'));
    }

    public function test_import_errors(): void
    {
        $this->artisan('translations:import')->expectsOutputToContain('Give a file or folder')->assertFailed();
        $this->artisan('translations:import', ['path' => '/nope.json'])->expectsOutputToContain('no such file')->assertFailed();

        $file = dirname($this->lang).'/notes.txt';
        file_put_contents($file, 'hi');
        $this->artisan('translations:import', ['path' => $file, '--locale' => 'fr'])->expectsOutputToContain('only .json and .php')->assertFailed();

        $this->expectException(ImportFailed::class);
        Translations::import(dirname($this->lang).'/notes.txt');
    }
}
