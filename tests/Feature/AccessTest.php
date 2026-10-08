<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Ruvelo\Translations\Tests\TestCase;

class AccessTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function routes(): array
    {
        return [
            'overview' => ['get', '/translations'],
            'editor' => ['get', '/translations/fr'],
            'changes' => ['get', '/translations/changes'],
            'save' => ['put', '/translations/fr/entries'],
            'revert' => ['delete', '/translations/fr/entries'],
            'add locale' => ['post', '/translations/locales'],
            'edit mode' => ['post', '/translations/edit-mode'],
            'suggest' => ['post', '/translations/fr/suggest'],
        ];
    }

    #[DataProvider('routes')]
    public function test_guests_get_a_403_when_the_app_has_no_login_page(string $method, string $uri): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->{$method}($uri, ['key' => 'billing.cancel', 'value' => 'x', 'code' => 'it'])->assertForbidden();
    }

    #[DataProvider('routes')]
    public function test_guests_go_to_the_login_page_when_there_is_one(string $method, string $uri): void
    {
        Route::get('/login', fn () => 'Sign in')->name('login');
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->{$method}($uri, ['key' => 'billing.cancel', 'value' => 'x'])->assertRedirect('/login');
    }

    public function test_guests_asking_for_json_get_a_401(): void
    {
        $this->putJson('/translations/fr/entries', ['key' => 'billing.cancel', 'value' => 'x'])->assertUnauthorized();
    }

    #[DataProvider('routes')]
    public function test_signed_in_users_need_the_gate(string $method, string $uri): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        // No gate defined: nobody, not even a would-be translator.
        $this->actingAs($this->user('Tom Reyes', translator: true))
            ->{$method}($uri, ['key' => 'billing.cancel', 'value' => 'x'])->assertForbidden();

        $this->editor('Inès Laurent');
        $this->actingAs($this->user('Kenji Mori'))
            ->{$method}($uri, ['key' => 'billing.cancel', 'value' => 'x'])->assertForbidden();
    }

    public function test_editors_get_in(): void
    {
        $this->actingAs($this->editor())->get('/translations')->assertOk();
    }

    public function test_unknown_locales_are_404s(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get('/translations/it')->assertNotFound();
        $this->actingAs($editor)->putJson('/translations/it/entries', ['key' => 'billing.cancel', 'value' => 'x'])->assertNotFound();
        $this->actingAs($editor)->get('/translations/not-a-locale')->assertNotFound();
    }

    public function test_the_path_is_configurable(): void
    {
        $this->assertStringEndsWith('/translations', route('translations.index'));
    }
}
