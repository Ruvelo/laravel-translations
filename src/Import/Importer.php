<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Import;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Ruvelo\Translations\Exceptions\ImportFailed;
use Ruvelo\Translations\Exceptions\InvalidKey;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Support\Catalogue;
use Ruvelo\Translations\Translations;
use Throwable;

/**
 * Brings translations in from outside as overrides: a file back from a
 * translator, another app's lang folder, or the database of
 * barryvdh/laravel-translation-manager. Nothing is written to your lang
 * files; the imported lines show as pending changes to review, then export.
 */
class Importer
{
    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly Filesystem $fs,
    ) {}

    /**
     * Import a .json file, a .php file, or a folder laid out like lang/.
     *
     * @param  string|null  $locale  Required for a single file
     * @param  string|null  $group  For a .php file: the file it belongs to (default: its name)
     *
     * @throws ImportFailed
     */
    public function fromPath(string $path, ?string $locale = null, ?string $group = null, ?string $namespace = null, ?Authenticatable $by = null): ImportResult
    {
        if ($this->fs->isDirectory($path)) {
            return $this->fromDirectory(rtrim($path, '/'), $locale, $by);
        }

        if (! $this->fs->isFile($path)) {
            throw new ImportFailed($path, 'there is no such file or folder.');
        }

        $locale ??= $this->guessLocale($path);
        if ($locale === null || ! Catalogue::isValidLocale($locale)) {
            throw new ImportFailed($path, 'say which locale it is with --locale.');
        }

        $lines = [];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'json') {
            $decoded = json_decode($this->fs->get($path), true);
            if (! is_array($decoded)) {
                throw new ImportFailed($path, 'it is not a JSON object of "key": "text" pairs.');
            }
            foreach ($decoded as $key => $value) {
                if (is_scalar($value)) {
                    $lines[] = [$locale, Key::JSON, '*', (string) $key, (string) $value];
                }
            }
        } elseif ($extension === 'php') {
            $group ??= basename($path, '.php');
            try {
                $array = (static fn (string $__path) => require $__path)($path);
            } catch (Throwable $e) {
                throw new ImportFailed($path, $e->getMessage());
            }
            if (! is_array($array)) {
                throw new ImportFailed($path, 'it does not return an array.');
            }
            foreach (Catalogue::flatten($array) as $key => $value) {
                $lines[] = [$locale, $group, $namespace ?? '*', $key, $value];
            }
        } else {
            throw new ImportFailed($path, 'only .json and .php files can be imported.');
        }

        return $this->apply($lines, $by);
    }

    /**
     * Import from barryvdh/laravel-translation-manager's table: rows with a
     * value become overrides. Its "_json" group is the JSON file; groups
     * like "vendor/courier/messages" belong to packages.
     *
     * @throws ImportFailed
     */
    public function fromTranslationManager(string $table = 'ltm_translations', ?string $connection = null, ?Authenticatable $by = null): ImportResult
    {
        try {
            $rows = DB::connection($connection)->table($table)->whereNotNull('value')->orderBy('id')->get(['locale', 'group', 'key', 'value']);
        } catch (Throwable $e) {
            throw new ImportFailed($table, 'the table can\'t be read ('.$e->getMessage().').');
        }

        $lines = [];
        foreach ($rows as $row) {
            $group = (string) $row->group;
            $namespace = '*';

            if ($group === '_json') {
                $group = Key::JSON;
            } elseif (preg_match('#^vendor/([^/]+)/(.+)$#', $group, $m) === 1) {
                [$namespace, $group] = [$m[1], $m[2]];
            }

            $lines[] = [(string) $row->locale, $group, $namespace, (string) $row->key, (string) $row->value];
        }

        return $this->apply($lines, $by);
    }

    private function fromDirectory(string $dir, ?string $only, ?Authenticatable $by): ImportResult
    {
        $lines = [];

        foreach ($this->fs->glob($dir.'/*.json') as $file) {
            $locale = basename($file, '.json');
            if (Catalogue::isValidLocale($locale) && ($only === null || $only === $locale)) {
                $decoded = json_decode($this->fs->get($file), true);
                foreach (is_array($decoded) ? $decoded : [] as $key => $value) {
                    if (is_scalar($value)) {
                        $lines[] = [$locale, Key::JSON, '*', (string) $key, (string) $value];
                    }
                }
            }
        }

        foreach ($this->fs->directories($dir) as $localeDir) {
            $locale = basename($localeDir);
            if ($locale === 'vendor' || ! Catalogue::isValidLocale($locale) || ($only !== null && $only !== $locale)) {
                continue;
            }

            foreach ($this->fs->allFiles($localeDir) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $group = str_replace('\\', '/', substr($file->getRelativePathname(), 0, -4));
                $array = (static fn (string $__path) => require $__path)($file->getPathname());
                foreach (is_array($array) ? Catalogue::flatten($array) : [] as $key => $value) {
                    $lines[] = [$locale, $group, '*', $key, $value];
                }
            }
        }

        return $this->apply($lines, $by);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string}>  $lines  [locale, group, namespace, key, value]
     */
    private function apply(array $lines, ?Authenticatable $by): ImportResult
    {
        $imported = [];
        $unchanged = 0;
        $skipped = [];

        foreach ($lines as [$locale, $group, $namespace, $item, $value]) {
            if (! Catalogue::isValidLocale($locale)) {
                $skipped[] = "“{$locale}” isn't a locale code.";

                continue;
            }

            try {
                $key = Key::fromParts($namespace, $group, $item);
            } catch (InvalidKey $e) {
                $skipped[] = $e->getMessage();

                continue;
            }

            if (! $this->catalogue->hasLocale($locale)) {
                Translations::addLocale($locale, $by);
            }

            $file = $this->catalogue->fileValue($locale, $key);

            if ($this->catalogue->effective($locale, $key) === $value || ($file === $value && $this->catalogue->override($locale, $key) === null)) {
                $unchanged++;

                continue;
            }

            Override::put($locale, $key, $value, $file, $by);
            $imported[] = ['locale' => $locale, 'key' => $key];
        }

        $this->catalogue->refresh();

        return new ImportResult($imported, $unchanged, $skipped);
    }

    private function guessLocale(string $path): ?string
    {
        $name = basename($path, '.'.pathinfo($path, PATHINFO_EXTENSION));
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json' && Catalogue::isValidLocale($name)) {
            return $name;
        }

        $parent = basename(dirname($path));

        return Catalogue::isValidLocale($parent) ? $parent : null;
    }
}
