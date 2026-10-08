<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Loader;

use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Support\OverrideCache;
use Throwable;

/**
 * Laravel's loader, with the overrides edited in the browser laid on top.
 *
 * Wraps whatever loader the app had (usually the FileLoader), so the lang
 * files stay the source of truth and an override only ever replaces the
 * lines it names.
 */
class OverrideLoader implements Loader
{
    public function __construct(private readonly Loader $loader) {}

    /**
     * @param  string  $locale
     * @param  string  $group
     * @param  string|null  $namespace
     * @return array<array-key, mixed>
     */
    public function load($locale, $group, $namespace = null)
    {
        $lines = $this->loader->load($locale, $group, $namespace);
        $namespace = $namespace === null || $namespace === '' ? '*' : $namespace;

        $overrides = $this->overrides((string) $locale, $namespace, (string) $group);

        if ($overrides === []) {
            return $lines;
        }

        if ($group === '*' && $namespace === '*') {
            return array_merge($lines, $overrides);
        }

        foreach ($overrides as $key => $value) {
            Arr::set($lines, $key, $value);
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function overrides(string $locale, string $namespace, string $group): array
    {
        // The same rules FileLoader applies, so nothing odd reaches the cache.
        if (preg_match('/^[A-Za-z0-9_\-]{1,20}$/', $locale) !== 1) {
            return [];
        }

        try {
            return OverrideCache::remember($locale, $namespace, $group, fn (): array => Override::query()
                ->forFile($locale, $namespace, $group)
                ->orderBy('id')
                ->pluck('value', 'key')
                ->map(fn ($value): string => (string) $value)
                ->all());
        } catch (Throwable) {
            // No table yet (before migrating), or the database is down: the
            // site keeps working on its lang files.
            return [];
        }
    }

    /**
     * @param  string  $namespace
     * @param  string  $hint
     */
    public function addNamespace($namespace, $hint): void
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    /**
     * @param  string  $path
     */
    public function addJsonPath($path): void
    {
        $this->loader->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces()
    {
        return $this->loader->namespaces();
    }

    /**
     * FileLoader's extras, which the Translator calls without them being on
     * the contract.
     *
     * @param  string  $path
     */
    public function addPath($path): void
    {
        if (method_exists($this->loader, 'addPath')) {
            $this->loader->addPath($path);
        }
    }

    /**
     * @return array<int, string>
     */
    public function paths(): array
    {
        return method_exists($this->loader, 'paths') ? $this->loader->paths() : [];
    }

    /**
     * @return array<int, string>
     */
    public function jsonPaths(): array
    {
        return method_exists($this->loader, 'jsonPaths') ? $this->loader->jsonPaths() : [];
    }

    public function inner(): Loader
    {
        return $this->loader;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->loader->{$method}(...$arguments);
    }
}
