<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Tests\TestCase;

class SwitchedOffTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('translations.enabled', false);
        $app['config']->set('translations.in_context.enabled', false);
    }

    public function test_laravels_own_loader_and_translator_stay_in_place(): void
    {
        $this->assertInstanceOf(FileLoader::class, app('translation.loader'));
        $this->assertSame(Translator::class, app('translator')::class);
    }

    public function test_overrides_are_not_applied(): void
    {
        Override::factory()->locale('fr')->key('billing.cancel')->value('Arrêter')->create();

        $this->assertSame("Résilier l'abonnement", __('billing.cancel', [], 'fr'));
    }

    public function test_no_toolbar_is_injected(): void
    {
        $this->actingAs($this->editor())->get('/translations')->assertOk();
        Route::middleware('web')->get('/page', fn () => view('page'));

        $this->get('/page')->assertOk()->assertDontSee('trans-toolbar');
    }
}
