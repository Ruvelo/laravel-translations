<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Translation\Translator;

/**
 * Compiled overrides, cached per locale and file. A version number in every
 * key lets flush() drop them all without knowing which exist.
 */
final class OverrideCache
{
    /**
     * @param  Closure(): array<string, string>  $compile
     * @return array<string, string>
     */
    public static function remember(string $locale, string $namespace, string $group, Closure $compile): array
    {
        if (! config('translations.cache.enabled', true)) {
            return $compile();
        }

        $store = self::store();
        $key = self::key($store, $locale, $namespace, $group);
        $cached = $store->get($key);

        if (is_array($cached)) {
            /** @var array<string, string> $cached */
            return $cached;
        }

        $lines = $compile();
        $ttl = config('translations.cache.ttl');
        $store->put($key, $lines, is_numeric($ttl) ? (int) $ttl : null);

        return $lines;
    }

    public static function forget(string $locale, string $namespace, string $group): void
    {
        if (config('translations.cache.enabled', true)) {
            $store = self::store();
            $store->forget(self::key($store, $locale, $namespace, $group));
        }

        self::reloadTranslator();
    }

    public static function flush(): void
    {
        if (config('translations.cache.enabled', true)) {
            $store = self::store();
            $store->forever('translations:version', self::version($store) + 1);
        }

        self::reloadTranslator();
    }

    /**
     * The translator keeps every file it loaded for the rest of the request
     * (or the worker's life, under Octane): make it read them again.
     */
    private static function reloadTranslator(): void
    {
        if (app()->resolved('translator') && ($translator = app('translator')) instanceof Translator) {
            $translator->setLoaded([]);
        }
    }

    private static function key(Repository $store, string $locale, string $namespace, string $group): string
    {
        return 'translations:'.self::version($store).':'.$locale.':'.$namespace.':'.$group;
    }

    private static function version(Repository $store): int
    {
        $version = $store->get('translations:version');

        return is_numeric($version) ? (int) $version : 1;
    }

    private static function store(): Repository
    {
        $store = config('translations.cache.store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }
}
