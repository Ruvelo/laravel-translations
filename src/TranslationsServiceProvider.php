<?php

declare(strict_types=1);

namespace Ruvelo\Translations;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;
use Ruvelo\Translations\Console\ExportCommand;
use Ruvelo\Translations\Console\ImportCommand;
use Ruvelo\Translations\Console\PruneCommand;
use Ruvelo\Translations\Console\ScanCommand;
use Ruvelo\Translations\Http\Middleware\InjectToolbar;
use Ruvelo\Translations\Loader\OverrideLoader;
use Ruvelo\Translations\Loader\RecordingTranslator;
use Ruvelo\Translations\Suggestions\LaravelAiSuggester;
use Ruvelo\Translations\Suggestions\Suggester;
use Ruvelo\Translations\Support\Catalogue;
use Ruvelo\Translations\Support\KeyRecorder;

class TranslationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/translations.php', 'translations');

        $this->app->scoped(Catalogue::class);
        $this->app->singleton(KeyRecorder::class);

        if (! $this->app->bound(Suggester::class) && LaravelAiSuggester::installed()) {
            $this->app->bind(Suggester::class, LaravelAiSuggester::class);
        }

        // The lang files stay the source of truth; overrides are laid over
        // whatever loader the app uses.
        $this->app->extend('translation.loader', function (Loader $loader) {
            return config('translations.enabled', true) && ! $loader instanceof OverrideLoader
                ? new OverrideLoader($loader)
                : $loader;
        });

        // Remember the keys each page uses, for in-context editing.
        $this->app->extend('translator', function ($translator, $app) {
            if (! config('translations.in_context.enabled', true) || ! $translator instanceof Translator || $translator instanceof RecordingTranslator) {
                return $translator;
            }

            // Only Laravel's own translator is swapped; a subclass from
            // another package is left as it is.
            if ($translator::class !== Translator::class) {
                return $translator;
            }

            $recording = new RecordingTranslator($translator->getLoader(), $translator->getLocale());
            $recording->setFallback($translator->getFallback());
            $recording->setRecorder($app->make(KeyRecorder::class));

            return $recording;
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'translations');

        View::composer('translations::partials.page', function ($view): void {
            $view->with('pendingCount', count(Translations::pending()));
        });

        // <x-translations::toolbar />
        Blade::componentNamespace('Ruvelo\\Translations\\View\\Components', 'translations');

        RateLimiter::for('translations-suggest', fn (Request $request) => Limit::perMinute(max(1, (int) config('translations.suggestions.per_minute', 30)))
            ->by('translations-suggest|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // A fresh list of keys, and fresh reads of the lang files, for every
        // request (Octane workers and tests reuse the app between them).
        Event::listen(RequestHandled::class, fn () => $this->app->make(KeyRecorder::class)->flush());
        Event::listen(RouteMatched::class, function (): void {
            if ($this->app->resolved(Catalogue::class)) {
                $this->app->make(Catalogue::class)->refresh();
            }
        });

        $this->app->make(Router::class)->aliasMiddleware('translations.toolbar', InjectToolbar::class);

        if (config('translations.in_context.enabled', true) && config('translations.in_context.inject', true)) {
            $this->app->booted(function () {
                $kernel = $this->app->make(HttpKernel::class);
                if (method_exists($kernel, 'appendMiddlewareToGroup')) {
                    $kernel->appendMiddlewareToGroup('web', InjectToolbar::class);
                }
            });
        }

        if (config('translations.routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        if (config('translations.api.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }

        if (config('translations.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([ExportCommand::class, ImportCommand::class, ScanCommand::class, PruneCommand::class]);

            $this->publishes([
                __DIR__.'/../config/translations.php' => config_path('translations.php'),
            ], 'translations-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/translations'),
            ], 'translations-views');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'translations-migrations');
        }
    }
}
