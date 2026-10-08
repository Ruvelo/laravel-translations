<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;

class JsonApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('translations.api.enabled', true);
        $app['config']->set('translations.api.middleware', ['api', 'auth']);
    }

    public function test_it_needs_a_user_and_the_gate(): void
    {
        $this->getJson('/api/translations/locales')->assertUnauthorized();
        $this->actingAs($this->user('Tom Reyes'))->getJson('/api/translations/locales')->assertForbidden();
    }

    public function test_locales(): void
    {
        $this->actingAs($this->editor())->getJson('/api/translations/locales')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0', ['code' => 'en', 'name' => 'English', 'locale' => 'en', 'total' => 10, 'translated' => 10, 'missing' => 0, 'percent' => 100, 'pending' => 0, 'source' => true])
            ->assertJsonPath('data.2.code', 'fr')
            ->assertJsonPath('data.2.missing', 4);
    }

    public function test_add_a_locale(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->postJson('/api/translations/locales', ['code' => 'nl'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'nl')
            ->assertJsonPath('data.percent', 0);

        $this->actingAs($editor)->postJson('/api/translations/locales', ['code' => 'nl'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'The “nl” locale already exists.');
    }

    public function test_entries(): void
    {
        $this->actingAs($this->editor())->getJson('/api/translations/locales/fr/entries?filter=missing&per_page=2')
            ->assertOk()
            ->assertJsonPath('meta', ['locale' => 'fr', 'source_locale' => 'en', 'total' => 4, 'per_page' => 2, 'current_page' => 1, 'last_page' => 2])
            ->assertJsonPath('data.0', [
                'locale' => 'fr',
                'key' => 'Sign out',
                'namespace' => '*',
                'group' => '*',
                'item' => 'Sign out',
                'source' => 'Sign out',
                'value' => null,
                'file_value' => null,
                'status' => 'missing',
                'pending' => false,
                'file_changed' => false,
                'warnings' => [],
                'updated_at' => null,
            ]);
    }

    public function test_entries_search_and_file(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->getJson('/api/translations/locales/fr/entries?q=facture')->assertJsonPath('meta.total', 1);
        $this->actingAs($editor)->getJson('/api/translations/locales/fr/entries?file=billing')->assertJsonPath('meta.total', 5);
        $this->actingAs($editor)->getJson('/api/translations/locales/fr/entries?filter=bogus')->assertJsonValidationErrors('filter');
        $this->actingAs($editor)->getJson('/api/translations/locales/it/entries')->assertNotFound();
    }

    public function test_update_and_destroy(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->putJson('/api/translations/locales/fr/entries', ['key' => 'billing.invoice.paid', 'value' => 'Payée'])
            ->assertOk()
            ->assertJsonPath('data.key', 'billing.invoice.paid')
            ->assertJsonPath('data.value', 'Payée')
            ->assertJsonPath('data.pending', true);

        $this->actingAs($editor)->putJson('/api/translations/locales/fr/entries', ['key' => 'Sign out', 'group' => '*', 'value' => 'Déconnexion'])
            ->assertOk()->assertJsonPath('data.group', '*');

        $this->actingAs($editor)->deleteJson('/api/translations/locales/fr/entries', ['key' => 'billing.invoice.paid'])->assertNoContent();
        $this->assertNull(Translations::get('fr', 'billing.invoice.paid'));
    }

    public function test_pending_changes_for_ci(): void
    {
        Translations::set('fr', 'billing.cancel', 'Arrêter');
        Translations::set('de', 'Pay now', 'Jetzt zahlen');

        $this->actingAs($this->editor())->getJson('/api/translations/changes')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['locale', 'key', 'value', 'file_value', 'status', 'updated_at']]]);

        $this->actingAs($this->editor('Inès Laurent'))->getJson('/api/translations/changes?locale=fr')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.file_value', "Résilier l'abonnement")
            ->assertJsonPath('data.0.value', 'Arrêter');
    }
}
