<?php

declare(strict_types=1);

namespace Ruvelo\Translations;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Ruvelo\Translations\Events\LocaleAdded;
use Ruvelo\Translations\Exceptions\ExportFailed;
use Ruvelo\Translations\Exceptions\ImportFailed;
use Ruvelo\Translations\Exceptions\InvalidKey;
use Ruvelo\Translations\Exceptions\InvalidLocale;
use Ruvelo\Translations\Exceptions\LocaleAlreadyExists;
use Ruvelo\Translations\Exceptions\SuggestionsUnavailable;
use Ruvelo\Translations\Export\Exporter;
use Ruvelo\Translations\Export\ExportResult;
use Ruvelo\Translations\Import\Importer;
use Ruvelo\Translations\Import\ImportResult;
use Ruvelo\Translations\Models\Locale;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Scanner\Scanner;
use Ruvelo\Translations\Scanner\ScanResult;
use Ruvelo\Translations\Suggestions\LaravelAiSuggester;
use Ruvelo\Translations\Suggestions\Suggester;
use Ruvelo\Translations\Support\Catalogue;
use Ruvelo\Translations\Support\KeyRecorder;
use Ruvelo\Translations\Support\OverrideCache;
use Ruvelo\Translations\Support\Placeholders;

/**
 * The package's public PHP API.
 *
 *     Translations::set('fr', 'billing.invoice.title', 'Facture');
 *     Translations::progress('fr')->percent();
 *     Translations::missing('de')->count();
 *     Translations::export(dryRun: true)->files;
 *
 * Keys are written as you'd pass them to __(): 'billing.invoice.title',
 * 'courier::messages.hello' or a JSON key like 'Pay now'. Pass a Key to be
 * explicit.
 */
final class Translations
{
    /**
     * The override for the key, if one was saved. null means the lang file
     * applies.
     *
     * @throws InvalidKey
     */
    public static function get(string $locale, Key|string $key): ?string
    {
        return self::catalogue()->override($locale, self::key($key, $locale))?->value;
    }

    /**
     * What the app shows for the key: the override, else the lang file.
     *
     * @throws InvalidKey
     */
    public static function value(string $locale, Key|string $key): ?string
    {
        return self::catalogue()->effective($locale, self::key($key, $locale));
    }

    /**
     * Save a translation. It applies at once; export it to put it in the
     * lang files. Saving the lang file's own text removes the override.
     *
     * @throws InvalidKey
     * @throws InvalidLocale
     */
    public static function set(string $locale, Key|string $key, string $value, ?Authenticatable $by = null): Entry
    {
        self::assertLocale($locale);
        $key = self::key($key, $locale);
        $catalogue = self::catalogue();
        $file = $catalogue->fileValue($locale, $key);

        if ($value === $file || ($value === '' && $file === null)) {
            Override::revert($locale, $key, $by, $file);
        } else {
            Override::put($locale, $key, $value, $file, $by);
        }

        $catalogue->refresh();

        return $catalogue->entry($locale, $key);
    }

    /**
     * Drop the override, so the lang file's text applies again.
     *
     * @throws InvalidKey
     */
    public static function forget(string $locale, Key|string $key, ?Authenticatable $by = null): bool
    {
        $key = self::key($key, $locale);
        $catalogue = self::catalogue();

        $forgotten = Override::revert($locale, $key, $by, $catalogue->fileValue($locale, $key));
        $catalogue->refresh();

        return $forgotten;
    }

    /**
     * @throws InvalidKey
     */
    public static function entry(string $locale, Key|string $key): Entry
    {
        return self::catalogue()->entry($locale, self::key($key, $locale));
    }

    /**
     * Every string in the locale. $filter is 'all', 'missing', 'changed' or
     * 'warnings'; $file is 'billing', 'courier::messages' or '*' for JSON.
     *
     * @return Collection<int, Entry>
     */
    public static function entries(string $locale, string $filter = 'all', string $search = '', ?string $file = null): Collection
    {
        return self::catalogue()->filter($locale, $filter, $search, $file);
    }

    /**
     * Strings the source locale has and this one doesn't (or has empty).
     *
     * @return Collection<int, Entry>
     */
    public static function missing(string $locale): Collection
    {
        return self::catalogue()->filter($locale, 'missing')
            ->filter(fn (Entry $entry) => $entry->source !== null)
            ->values();
    }

    public static function progress(string $locale): Progress
    {
        return self::catalogue()->progress($locale);
    }

    /**
     * Edits not yet in the lang files, newest first.
     *
     * @return list<Entry>
     */
    public static function pending(?string $locale = null): array
    {
        return self::catalogue()->pending($locale);
    }

    /**
     * The source locale first, then the rest alphabetically.
     *
     * @return list<string>
     */
    public static function locales(): array
    {
        return self::catalogue()->locales();
    }

    public static function sourceLocale(): string
    {
        return self::catalogue()->sourceLocale();
    }

    /**
     * Add a locale. It lists as 0% translated until strings are saved; an
     * export creates its files.
     *
     * @throws InvalidLocale
     * @throws LocaleAlreadyExists
     */
    public static function addLocale(string $locale, ?Authenticatable $by = null): string
    {
        $locale = trim($locale);

        if (! Catalogue::isValidLocale($locale)) {
            throw new InvalidLocale($locale);
        }

        $catalogue = self::catalogue();
        if ($catalogue->hasLocale($locale)) {
            throw new LocaleAlreadyExists($locale);
        }

        Locale::query()->create(['code' => $locale]);
        $catalogue->refresh();

        event(new LocaleAdded($locale, $by));

        return $locale;
    }

    /**
     * Write pending changes into the lang files. With $dryRun, nothing is
     * written and the result holds the diffs.
     *
     * @param  list<string>|string|null  $locales
     *
     * @throws ExportFailed
     */
    public static function export(array|string|null $locales = null, bool $dryRun = false, bool $prune = false): ExportResult
    {
        return app(Exporter::class)->export(is_string($locales) ? [$locales] : $locales, $dryRun, $prune);
    }

    /**
     * Delete overrides that match their lang file again (after an export
     * was deployed). Returns how many.
     */
    public static function prune(): int
    {
        return app(Exporter::class)->prune();
    }

    /**
     * Bring in a .json or .php file, or a folder laid out like lang/, as
     * pending changes.
     *
     * @throws ImportFailed
     */
    public static function import(string $path, ?string $locale = null, ?string $group = null, ?Authenticatable $by = null): ImportResult
    {
        return app(Importer::class)->fromPath($path, $locale, $group, by: $by);
    }

    /**
     * Find the keys your code uses: what's missing from the source locale,
     * and what nothing seems to use.
     *
     * @param  list<string>|null  $paths
     */
    public static function scan(?array $paths = null, ?bool $js = null): ScanResult
    {
        return app(Scanner::class)->scan($paths, $js);
    }

    /**
     * Warnings about placeholders and plural forms the translation dropped
     * or added.
     *
     * @return list<string>
     */
    public static function check(string $source, string $translation): array
    {
        return Placeholders::check($source, $translation);
    }

    public static function canSuggest(): bool
    {
        if (! config('translations.suggestions.enabled', true)) {
            return false;
        }

        if (! app()->bound(Suggester::class)) {
            return false;
        }

        // Your own Suggester is trusted to be ready; the AI SDK one needs a
        // provider set up.
        return ! app(Suggester::class) instanceof LaravelAiSuggester || LaravelAiSuggester::configured();
    }

    /**
     * A drafted translation of the source text. Never saved by itself.
     *
     * @throws SuggestionsUnavailable
     * @throws InvalidKey
     */
    public static function suggest(string $locale, Key|string $key): string
    {
        if (! self::canSuggest()) {
            throw new SuggestionsUnavailable;
        }

        $key = self::key($key, $locale);
        $source = self::catalogue()->effective(self::sourceLocale(), $key);

        if ($source === null || trim($source) === '') {
            throw new SuggestionsUnavailable('There is no source text to translate.');
        }

        return app(Suggester::class)->suggest($source, self::sourceLocale(), $locale, $key->full());
    }

    /**
     * Whether the user may use the editor. Define a `translations-edit`
     * gate to decide; without one, nobody can.
     */
    public static function canEdit(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && Gate::has('translations-edit') && Gate::forUser($user)->allows('translations-edit');
    }

    /**
     * The keys used so far to render this request.
     *
     * @return list<string>
     */
    public static function recordedKeys(): array
    {
        return app(KeyRecorder::class)->keys();
    }

    /**
     * Turn a key as written in __() into a Key.
     *
     * @throws InvalidKey
     */
    public static function key(Key|string $key, ?string $locale = null): Key
    {
        return $key instanceof Key ? $key : self::catalogue()->resolve($key, $locale);
    }

    public static function localeName(string $locale): string
    {
        return self::catalogue()->localeName($locale);
    }

    /**
     * Forget every compiled override, e.g. after editing the table by hand.
     */
    public static function flushCache(): void
    {
        OverrideCache::flush();
        self::catalogue()->refresh();
    }

    /**
     * @return 'layout'|'embedded'
     */
    public static function layout(): string
    {
        return config('translations.layout') ? 'embedded' : 'layout';
    }

    public static function catalogue(): Catalogue
    {
        return app(Catalogue::class);
    }

    /**
     * @throws InvalidLocale
     */
    private static function assertLocale(string $locale): void
    {
        if (! Catalogue::isValidLocale($locale)) {
            throw new InvalidLocale($locale);
        }

        if (! self::catalogue()->hasLocale($locale)) {
            throw InvalidLocale::unknown($locale);
        }
    }
}
