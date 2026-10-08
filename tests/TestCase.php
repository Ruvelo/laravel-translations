<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Ruvelo\Translations\Tests\Fixtures\User;
use Ruvelo\Translations\TranslationsServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected string $lang = '';

    protected function setUp(): void
    {
        // A fresh copy of the fixture lang folder per test: exports write to it.
        $this->lang = sys_get_temp_dir().'/ruvelo-translations-'.bin2hex(random_bytes(6)).'/lang';
        (new Filesystem)->copyDirectory(__DIR__.'/Fixtures/lang', $this->lang);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->lang !== '') {
            (new Filesystem)->deleteDirectory(dirname($this->lang));
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [TranslationsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->useLangPath($this->lang);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));
        $app['config']->set('app.locale', 'en');
        $app['config']->set('app.fallback_locale', 'en');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('view.paths', [__DIR__.'/Fixtures/views', ...$app['config']->get('view.paths', [])]);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_translator')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function user(string $name = 'Ada', bool $translator = false): User
    {
        return User::forceCreate([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
            'password' => 'secret',
            'is_translator' => $translator,
        ]);
    }

    /**
     * Defines the gate the way a host app would, and returns an editor.
     */
    protected function editor(string $name = 'Maya Okafor'): User
    {
        Gate::define('translations-edit', fn (User $user) => (bool) $user->is_translator);

        return $this->user($name, translator: true);
    }

    protected function langFile(string $relative): string
    {
        return (string) file_get_contents($this->lang.'/'.$relative);
    }
}
