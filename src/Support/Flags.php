<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Support;

/**
 * Round language flags for the UI, from the bundled circle-flags set.
 *
 * A locale's region wins when there's a flag for it (pt_BR → pt-br), then
 * its language (fr_CA → fr). `translations.flags` can map a locale to
 * another flag (e.g. 'en' => 'en-us'), or be false to show none.
 */
final class Flags
{
    /** @var array<string, string|null> */
    private static array $cache = [];

    /**
     * The flag as a data: URI for an <img>, or null when there isn't one.
     * Data URIs keep each SVG's internal ids from clashing on one page.
     */
    public static function for(string $locale): ?string
    {
        $setting = config('translations.flags', []);
        if ($setting === false) {
            return null;
        }

        if (array_key_exists($locale, self::$cache)) {
            return self::$cache[$locale];
        }

        $overrides = is_array($setting) ? $setting : [];
        $normalized = strtolower(str_replace('_', '-', $locale));
        $candidates = array_filter([
            is_string($overrides[$locale] ?? null) ? $overrides[$locale] : null,
            $normalized,
            explode('-', $normalized)[0],
        ]);

        foreach ($candidates as $name) {
            if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $name) !== 1) {
                continue;
            }

            $file = __DIR__.'/../../resources/flags/'.$name.'.svg';
            if (is_file($file)) {
                return self::$cache[$locale] = 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents($file));
            }
        }

        return self::$cache[$locale] = null;
    }

    /**
     * Forget resolved flags, e.g. after changing the config in a test.
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
