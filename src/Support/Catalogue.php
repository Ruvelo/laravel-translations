<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Ruvelo\Translations\Entry;
use Ruvelo\Translations\Exceptions\InvalidKey;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Loader\OverrideLoader;
use Ruvelo\Translations\Models\Locale;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Progress;
use Throwable;

/**
 * Everything there is to translate: the lang files on disk and the
 * overrides in the database, read the way Laravel's FileLoader reads them.
 *
 * Results are memoised for the life of the instance (one request); writes
 * through the Translations API call refresh().
 */
class Catalogue
{
    public const LOCALE_PATTERN = '/^[a-z]{2,3}([_-][A-Za-z0-9]{2,8}){0,3}$/';

    /** @var array<string, array<string, string>> */
    private array $files = [];

    /** @var array<string, array<string, Override>>|null */
    private ?array $overrides = null;

    /** @var list<array{0: string, 1: string}>|null */
    private ?array $groups = null;

    /** @var array<string, list<Entry>> */
    private array $entries = [];

    public function __construct(private readonly Filesystem $fs) {}

    public function refresh(): void
    {
        $this->files = [];
        $this->overrides = null;
        $this->groups = null;
        $this->entries = [];
    }

    public function langPath(): string
    {
        $path = config('translations.lang_path');

        return rtrim(is_string($path) && $path !== '' ? $path : app()->langPath(), '/');
    }

    public function sourceLocale(): string
    {
        $locale = config('translations.source_locale') ?? config('app.fallback_locale') ?? 'en';

        return is_string($locale) && $locale !== '' ? $locale : 'en';
    }

    /**
     * The source locale first, then the others alphabetically.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        $configured = config('translations.locales');
        $locales = is_array($configured) ? array_values(array_filter($configured, 'is_string')) : $this->detectLocales();

        try {
            $locales = [...$locales, ...Locale::query()->pluck('code')->all()];
        } catch (Throwable) {
            // Not migrated yet.
        }

        $locales = array_values(array_unique(array_filter($locales, fn ($locale) => is_string($locale) && self::isValidLocale($locale))));
        sort($locales);

        $source = $this->sourceLocale();

        return [$source, ...array_values(array_diff($locales, [$source]))];
    }

    public function hasLocale(string $locale): bool
    {
        return in_array($locale, $this->locales(), true);
    }

    public static function isValidLocale(string $locale): bool
    {
        return preg_match(self::LOCALE_PATTERN, $locale) === 1;
    }

    /**
     * A readable name: "Français (fr)" style names come from ext-intl.
     */
    public function localeName(string $locale): string
    {
        $names = config('translations.names', []);
        if (is_array($names) && isset($names[$locale]) && is_string($names[$locale])) {
            return $names[$locale];
        }

        if (function_exists('locale_get_display_name')) {
            $name = locale_get_display_name($locale, 'en');
            if (is_string($name) && $name !== $locale) {
                return $name;
            }
        }

        return $locale;
    }

    /**
     * Every file across all locales, as [namespace, group] pairs; the JSON
     * file is ['*', '*'].
     *
     * @return list<array{0: string, 1: string}>
     */
    public function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $found = [];
        $path = $this->langPath();

        foreach ($this->locales() as $locale) {
            if ($this->fs->exists("{$path}/{$locale}.json")) {
                $found['*'] = ['*', '*'];
            }

            foreach ($this->phpFiles("{$path}/{$locale}") as $group) {
                $found[$group] = ['*', $group];
            }

            foreach ($this->namespaces() as $namespace => $hint) {
                foreach ([...($hint !== null ? [$hint.'/'.$locale] : []), "{$path}/vendor/{$namespace}/{$locale}"] as $dir) {
                    foreach ($this->phpFiles($dir) as $group) {
                        $found[$namespace.'::'.$group] = [$namespace, $group];
                    }
                }
            }
        }

        foreach ($this->allOverrides() as $overrides) {
            foreach ($overrides as $override) {
                $found[Key::fileId($override->namespace, $override->group)] = [$override->namespace, $override->group];
            }
        }

        $ignored = array_map('strval', (array) config('translations.ignore_groups', []));
        $found = array_filter($found, fn (array $file, string $id) => ! in_array($id, $ignored, true), ARRAY_FILTER_USE_BOTH);

        // JSON first, then the app's files, then packages.
        uksort($found, fn (string $a, string $b) => [$a !== '*', str_contains($a, '::'), $a] <=> [$b !== '*', str_contains($b, '::'), $b]);

        return $this->groups = array_values($found);
    }

    /**
     * Packages whose translations are included: lang/vendor/* folders, and
     * the registered namespaces named in config (with their own lang path).
     *
     * @return array<string, string|null>
     */
    public function namespaces(): array
    {
        $namespaces = [];

        if (config('translations.vendor', true) && $this->fs->isDirectory($vendor = $this->langPath().'/vendor')) {
            foreach ($this->fs->directories($vendor) as $dir) {
                $namespaces[basename($dir)] = null;
            }
        }

        $hints = $this->loaderHints();
        foreach ((array) config('translations.namespaces', []) as $namespace) {
            if (is_string($namespace)) {
                $namespaces[$namespace] = $hints[$namespace] ?? null;
            }
        }

        foreach ($namespaces as $namespace => $hint) {
            $namespaces[$namespace] ??= $hints[$namespace] ?? null;
        }

        ksort($namespaces);

        return $namespaces;
    }

    /**
     * What the lang files say for one file in one locale, flattened to dot
     * keys (JSON keys as they are).
     *
     * @return array<string, string>
     */
    public function fileLines(string $locale, string $namespace, string $group): array
    {
        $id = $locale.'|'.$namespace.'|'.$group;

        if (isset($this->files[$id])) {
            return $this->files[$id];
        }

        if (! self::isValidLocale($locale)) {
            return $this->files[$id] = [];
        }

        $path = $this->langPath();

        if ($group === '*') {
            return $this->files[$id] = $this->readJson("{$path}/{$locale}.json");
        }

        if ($namespace === '*') {
            return $this->files[$id] = self::flatten($this->readPhp("{$path}/{$locale}/{$group}.php"));
        }

        // A package's own lines, then the app's lang/vendor overrides on top.
        $hint = $this->namespaces()[$namespace] ?? null;
        $lines = $hint !== null ? $this->readPhp("{$hint}/{$locale}/{$group}.php") : [];
        $lines = array_replace_recursive($lines, $this->readPhp("{$path}/vendor/{$namespace}/{$locale}/{$group}.php"));

        return $this->files[$id] = self::flatten($lines);
    }

    public function fileValue(string $locale, Key $key): ?string
    {
        return $this->fileLines($locale, $key->namespace, $key->group)[$key->item] ?? null;
    }

    public function override(string $locale, Key $key): ?Override
    {
        return $this->allOverrides()[$locale][$key->hash()] ?? null;
    }

    /**
     * @return array<string, Override>
     */
    public function overrides(string $locale): array
    {
        return $this->allOverrides()[$locale] ?? [];
    }

    public function entry(string $locale, Key $key): Entry
    {
        return new Entry(
            $locale,
            $key,
            $this->effective($this->sourceLocale(), $key),
            $this->fileValue($locale, $key),
            $this->override($locale, $key),
        );
    }

    /**
     * What the app shows for the key in the locale: override, else file.
     */
    public function effective(string $locale, Key $key): ?string
    {
        return $this->override($locale, $key)->value ?? $this->fileValue($locale, $key);
    }

    /**
     * Every key of every file in the locale, plus the source locale's keys it
     * doesn't have yet, in file order.
     *
     * @return list<Entry>
     */
    public function entries(string $locale): array
    {
        if (isset($this->entries[$locale])) {
            return $this->entries[$locale];
        }

        $source = $this->sourceLocale();
        $overrides = $this->overrides($locale);
        $sourceOverrides = $this->overrides($source);
        $entries = [];

        foreach ($this->groups() as [$namespace, $group]) {
            $sourceLines = $this->fileLines($source, $namespace, $group);
            $lines = $this->fileLines($locale, $namespace, $group);

            $items = array_keys($sourceLines + $lines);
            foreach ([$sourceOverrides, $overrides] as $set) {
                foreach ($set as $override) {
                    if ($override->namespace === $namespace && $override->group === $group && ! array_key_exists($override->key, $sourceLines + $lines)) {
                        $items[] = $override->key;
                    }
                }
            }

            foreach (array_unique(array_map('strval', $items)) as $item) {
                try {
                    $key = Key::fromParts($namespace, $group, $item);
                } catch (InvalidKey) {
                    continue;
                }

                $hash = $key->hash();
                $entries[] = new Entry(
                    $locale,
                    $key,
                    isset($sourceOverrides[$hash]) ? $sourceOverrides[$hash]->value : ($sourceLines[$item] ?? null),
                    $lines[$item] ?? null,
                    $overrides[$hash] ?? null,
                );
            }
        }

        return $this->entries[$locale] = $entries;
    }

    /**
     * @return Collection<int, Entry>
     */
    public function filter(string $locale, string $filter = 'all', string $search = '', ?string $file = null): Collection
    {
        return collect($this->entries($locale))
            ->filter(fn (Entry $entry) => $file === null || $file === '' || $entry->key->file() === $file)
            ->filter(fn (Entry $entry) => match ($filter) {
                'missing' => $entry->isMissing(),
                'changed' => $entry->isPending(),
                'warnings' => $entry->warnings() !== [],
                default => true,
            })
            ->filter(fn (Entry $entry) => $entry->matches($search))
            ->values();
    }

    public function progress(string $locale): Progress
    {
        $source = $this->sourceLocale();
        $total = $translated = $pending = 0;

        foreach ($this->entries($locale) as $entry) {
            if ($entry->isPending()) {
                $pending++;
            }

            // Only keys the source locale has count: extra keys in a
            // translation don't make it more finished.
            if ($entry->source === null || ($locale !== $source && trim($entry->source) === '')) {
                continue;
            }

            $total++;
            if (! $entry->isMissing()) {
                $translated++;
            }
        }

        return new Progress($locale, $total, $translated, $pending, $locale === $source);
    }

    /**
     * Overrides that differ from the lang files: the changes an export would
     * write, newest first.
     *
     * @return list<Entry>
     */
    public function pending(?string $locale = null): array
    {
        $pending = [];

        foreach ($this->allOverrides() as $code => $overrides) {
            if ($locale !== null && $code !== $locale) {
                continue;
            }

            foreach ($overrides as $override) {
                try {
                    $entry = $this->entry($code, $override->translationKey());
                } catch (InvalidKey) {
                    continue;
                }

                if ($entry->isPending()) {
                    $pending[] = $entry;
                }
            }
        }

        usort($pending, fn (Entry $a, Entry $b) => [$b->override?->updated_at?->getTimestamp(), $b->override?->id] <=> [$a->override?->updated_at?->getTimestamp(), $a->override?->id]);

        return $pending;
    }

    /**
     * Turn what someone typed into __() into a key. A string is a JSON key
     * when the JSON file has it, or when it doesn't look like "file.key" with
     * a file that exists.
     *
     * @throws InvalidKey
     */
    public function resolve(string $key, ?string $locale = null): Key
    {
        $locale ??= $this->sourceLocale();

        if (array_key_exists($key, $this->fileLines($locale, '*', '*'))
            || array_key_exists($key, $this->fileLines($this->sourceLocale(), '*', '*'))) {
            return Key::json($key);
        }

        if (preg_match('/^(?:([A-Za-z0-9][A-Za-z0-9_.\-]*)::)?([A-Za-z0-9_\-]+(?:\/[A-Za-z0-9_\-]+)*)\.(\S.*)$/s', $key, $m) === 1
            && ! str_contains($m[3], ' ')) {
            $namespace = $m[1] !== '' ? $m[1] : '*';

            if ($namespace !== '*' || in_array(['*', $m[2]], $this->groups(), true)) {
                return Key::group($m[2], $m[3], $namespace);
            }
        }

        return Key::json($key);
    }

    /**
     * @return array<string, array<string, Override>>
     */
    private function allOverrides(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        $all = [];

        try {
            foreach (Override::query()->orderBy('id')->get() as $override) {
                $all[$override->locale][$override->hash] = $override;
            }
        } catch (Throwable) {
            // Not migrated yet: no overrides.
        }

        return $this->overrides = $all;
    }

    /**
     * @return list<string>
     */
    private function detectLocales(): array
    {
        $path = $this->langPath();
        $locales = [];

        if ($this->fs->isDirectory($path)) {
            foreach ($this->fs->directories($path) as $dir) {
                if (basename($dir) !== 'vendor') {
                    $locales[] = basename($dir);
                }
            }

            foreach ($this->fs->glob($path.'/*.json') as $file) {
                $locales[] = basename($file, '.json');
            }

            if (config('translations.vendor', true)) {
                foreach ($this->fs->glob($path.'/vendor/*/*', GLOB_ONLYDIR) as $dir) {
                    $locales[] = basename($dir);
                }
            }
        }

        return $locales;
    }

    /**
     * Group names of the PHP files under a locale folder, including
     * sub-folders ("admin/users").
     *
     * @return list<string>
     */
    private function phpFiles(string $dir): array
    {
        if (! $this->fs->isDirectory($dir)) {
            return [];
        }

        $groups = [];
        foreach ($this->fs->allFiles($dir) as $file) {
            if ($file->getExtension() === 'php') {
                $groups[] = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -4));
            }
        }
        sort($groups);

        return $groups;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readPhp(string $file): array
    {
        if (! $this->fs->isFile($file)) {
            return [];
        }

        $lines = (static fn (string $__path) => require $__path)($file);

        return is_array($lines) ? $lines : [];
    }

    /**
     * @return array<string, string>
     */
    private function readJson(string $file): array
    {
        if (! $this->fs->isFile($file)) {
            return [];
        }

        $decoded = json_decode($this->fs->get($file), true);
        $lines = [];

        foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
            if (is_scalar($value)) {
                $lines[(string) $key] = (string) $value;
            }
        }

        return $lines;
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @return array<string, string>
     */
    public static function flatten(array $lines): array
    {
        $flat = [];

        foreach (Arr::dot($lines) as $key => $value) {
            if (is_scalar($value)) {
                $flat[(string) $key] = is_bool($value) ? ($value ? '1' : '') : (string) $value;
            }
        }

        return $flat;
    }

    /**
     * @return array<string, string>
     */
    private function loaderHints(): array
    {
        $loader = app('translation.loader');

        if ($loader instanceof OverrideLoader) {
            $loader = $loader->inner();
        }

        $hints = [];
        foreach ($loader->namespaces() as $namespace => $hint) {
            if (is_string($namespace) && is_string($hint)) {
                $hints[$namespace] = rtrim($hint, '/');
            }
        }

        return $hints;
    }
}
