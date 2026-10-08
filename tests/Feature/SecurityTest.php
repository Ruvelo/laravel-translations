<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;

class SecurityTest extends TestCase
{
    private const XSS = '<script>alert(1)</script><img src=x onerror=alert(2)>';

    public function test_html_in_values_is_escaped_everywhere_in_the_ui(): void
    {
        $editor = $this->editor();
        Translations::set('fr', 'billing.cancel', self::XSS);
        Translations::set('en', 'billing.invoice.paid', self::XSS);

        foreach (['/translations/fr', '/translations/changes', '/translations/en'] as $url) {
            $this->actingAs($editor)->get($url)
                ->assertOk()
                ->assertDontSee(self::XSS, false)
                ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        }
    }

    public function test_keys_are_escaped_too(): void
    {
        $editor = $this->editor();
        Translations::set('fr', '<b onmouseover=alert(1)>Hi</b>', 'Salut');

        $this->actingAs($editor)->get('/translations/fr')
            ->assertOk()
            ->assertDontSee('<b onmouseover=alert(1)>', false);
    }

    public function test_the_in_context_panel_escapes_values(): void
    {
        $editor = $this->editor();
        Translations::set('en', 'Sign out', self::XSS);

        $this->actingAs($editor)->withSession(['translations.edit_mode' => true]);
        Route::middleware('web')->get('/app', fn () => view('app'));

        $html = (string) $this->get('/app')->assertOk()->getContent();
        // The page itself prints {{ __('Sign out') }}, escaped by Blade; the
        // panel must not print it raw either.
        $this->assertStringNotContainsString(self::XSS, $html);
    }

    public function test_writes_need_a_csrf_token(): void
    {
        $this->actingAs($this->editor());

        // Testbench skips CSRF in unit tests unless asked.
        $this->app['env'] = 'production';
        $this->put('/translations/fr/entries', ['key' => 'billing.cancel', 'value' => 'x'])->assertStatus(419);
    }

    public function test_locale_codes_cannot_reach_other_folders(): void
    {
        $this->actingAs($this->editor());

        $this->post('/translations/locales', ['code' => '../../etc'])->assertSessionHasErrors('code');
        $this->assertFalse(file_exists(dirname($this->lang).'/etc.json'));
    }
}
