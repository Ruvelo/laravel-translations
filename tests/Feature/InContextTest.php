<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Ruvelo\Translations\Support\KeyRecorder;
use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;
use Ruvelo\Translations\View\Components\Toolbar;

class InContextTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/invoice', fn () => view('page'));
        $router->middleware('web')->get('/app', fn () => view('app'));
        $router->middleware('web')->get('/data', fn () => ['title' => __('Pay now')]);
        $router->middleware('web')->get('/text', fn () => response(__('Pay now'))->header('Content-Type', 'text/plain'));
    }

    public function test_the_keys_a_page_uses_are_recorded(): void
    {
        $recorder = app(KeyRecorder::class);

        view('page')->render();

        $this->assertSame([
            'billing.invoice.title',
            'Welcome back, :name!',
            'billing.invoice.due',
            'billing.plans',
            'Pay now',
            'billing.missing_line',
        ], $recorder->keys());
        $this->assertSame($recorder->keys(), Translations::recordedKeys());
    }

    public function test_has_checks_and_validation_probes_are_not_recorded(): void
    {
        app('translator')->has('billing.cancel');
        __('validation.custom.email.required');
        __('validation.attributes.email');

        $this->assertSame([], Translations::recordedKeys());
    }

    public function test_recording_is_capped(): void
    {
        config()->set('translations.in_context.max_keys', 2);

        foreach (['a.b', 'c.d', 'e.f'] as $key) {
            __($key);
        }

        $this->assertSame(['a.b', 'c.d'], Translations::recordedKeys());
    }

    public function test_the_list_is_fresh_for_every_request(): void
    {
        $this->get('/invoice')->assertOk();

        $this->assertSame([], Translations::recordedKeys());
    }

    public function test_editors_get_the_button_on_every_page(): void
    {
        $this->actingAs($this->editor())
            ->get('/invoice')
            ->assertOk()
            ->assertSee(Toolbar::MARKER, false)
            ->assertSee('Edit translations')
            ->assertDontSee('Translations on this page');
    }

    public function test_everyone_else_gets_nothing(): void
    {
        $this->get('/invoice')->assertOk()->assertDontSee(Toolbar::MARKER, false);
        $this->actingAs($this->user('Tom Reyes'))->get('/invoice')->assertOk()->assertDontSee(Toolbar::MARKER, false);

        $this->editor();
        $this->actingAs($this->user('Kenji Mori'))->get('/invoice')->assertOk()->assertDontSee(Toolbar::MARKER, false);
    }

    public function test_non_html_responses_are_left_alone(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get('/data')->assertOk()->assertExactJson(['title' => 'Pay now']);
        $this->actingAs($editor)->get('/text')->assertOk()->assertSee('Pay now')->assertDontSee(Toolbar::MARKER, false);
        $this->actingAs($editor)->get('/translations')->assertOk()->assertDontSee(Toolbar::MARKER, false);
    }

    public function test_edit_mode_lists_the_strings_on_the_page(): void
    {
        $this->actingAs($this->editor())->from('/invoice')
            ->post('/translations/edit-mode', ['on' => '1'])
            ->assertRedirect('/invoice')
            ->assertSessionHas('translations.edit_mode', true);

        app()->setLocale('fr');

        $this->get('/invoice')
            ->assertOk()
            ->assertSee('Translations on this page')
            ->assertSee('6 strings in French')
            ->assertSee('2 missing</span>', false)
            ->assertSeeInOrder(['billing.invoice.title', 'Welcome back, :name!', 'billing.invoice.due', 'billing.plans', 'Pay now', 'billing.missing_line'])
            ->assertSee('Facture :number')
            ->assertSee('data-label="English"', false)
            ->assertSee('action="http://localhost/translations/fr/entries"', false);
    }

    public function test_the_panel_saves_and_turns_off(): void
    {
        $editor = $this->editor();
        $this->actingAs($editor)->withSession(['translations.edit_mode' => true]);

        $this->from('/invoice')
            ->put('/translations/en/entries', ['namespace' => '*', 'group' => '*', 'key' => 'Pay now', 'value' => 'Pay the invoice'])
            ->assertRedirect('/invoice');
        $this->get('/invoice')->assertSee('Pay the invoice');

        $this->from('/invoice')->postJson('/translations/edit-mode', ['on' => '0'])->assertOk()->assertExactJson(['on' => false]);
        $this->get('/invoice')->assertDontSee('Translations on this page')->assertSee('Edit translations');
    }

    public function test_the_component_renders_once_with_the_keys_used_before_it(): void
    {
        config()->set('translations.in_context.inject', false);
        $this->actingAs($this->editor())->withSession(['translations.edit_mode' => true]);

        $html = (string) $this->get('/app')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, Toolbar::MARKER));
        $this->assertStringContainsString('1 string in English', $html);
        $this->assertStringContainsString('Sign out', $html);
    }

    public function test_the_component_is_empty_for_non_editors(): void
    {
        $this->assertSame('', trim(Blade::render('<x-translations::toolbar />')));
        $this->assertSame('', Toolbar::html());
    }

    public function test_a_locale_that_is_not_one_of_yours(): void
    {
        $this->actingAs($this->editor())->withSession(['translations.edit_mode' => true]);
        Route::middleware('web')->get('/italian', function () {
            app()->setLocale('it');

            return view('page');
        });

        $this->get('/italian')->assertOk()->assertSee('“it” isn\'t one of your languages.', false);
    }

    public function test_a_page_with_no_strings(): void
    {
        $this->actingAs($this->editor())->withSession(['translations.edit_mode' => true]);
        Route::middleware('web')->get('/blank', fn () => '<html><body><p>Hi</p></body></html>');

        $this->get('/blank')->assertOk()->assertSee('No strings on this page.');
    }
}
