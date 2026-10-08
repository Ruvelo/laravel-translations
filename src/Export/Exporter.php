<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Export;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Ruvelo\Translations\Entry;
use Ruvelo\Translations\Events\TranslationsExported;
use Ruvelo\Translations\Exceptions\ExportFailed;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Support\Catalogue;
use Ruvelo\Translations\Support\OverrideCache;
use Throwable;

/**
 * Writes pending overrides into the lang files, so they can be committed.
 */
class Exporter
{
    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly Filesystem $fs,
    ) {}

    /**
     * @param  list<string>|null  $locales  null for all
     * @param  bool  $prune  Delete the overrides once the files hold them
     *
     * @throws ExportFailed
     */
    public function export(?array $locales = null, bool $dryRun = false, bool $prune = false): ExportResult
    {
        /** @var array<string, non-empty-list<Entry>> $byFile */
        $byFile = [];
        $entries = [];

        foreach ($this->catalogue->pending() as $entry) {
            if ($locales !== null && ! in_array($entry->locale, $locales, true)) {
                continue;
            }

            $byFile[$this->relativePath($entry->locale, $entry->key)][] = $entry;
            $entries[] = $entry;
        }

        ksort($byFile);

        $files = [];
        foreach ($byFile as $relative => $fileEntries) {
            $files[] = $this->build($relative, $fileEntries);
        }

        $pruned = 0;

        if (! $dryRun) {
            foreach ($files as $file) {
                $this->write($file);
            }

            if ($prune && $entries !== []) {
                $ids = array_values(array_filter(array_map(fn (Entry $entry) => $entry->override?->id, $entries)));
                $pruned = Override::query()->whereKey($ids)->delete();
                OverrideCache::flush();
            }

            $this->catalogue->refresh();
        }

        $result = new ExportResult($files, $entries, $dryRun, $pruned);

        if (! $dryRun && $entries !== []) {
            event(new TranslationsExported($result));
        }

        return $result;
    }

    /**
     * Overrides that match their lang file again (after an export was
     * committed and deployed) do nothing: delete them.
     */
    public function prune(): int
    {
        $ids = [];

        foreach ($this->catalogue->locales() as $locale) {
            foreach ($this->catalogue->overrides($locale) as $override) {
                try {
                    $key = $override->translationKey();
                } catch (Throwable) {
                    continue;
                }

                if ($this->catalogue->fileValue($locale, $key) === $override->value) {
                    $ids[] = $override->id;
                }
            }
        }

        $count = $ids === [] ? 0 : Override::query()->whereKey($ids)->delete();

        if ($count > 0) {
            OverrideCache::flush();
            $this->catalogue->refresh();
        }

        return $count;
    }

    /**
     * Where the key's file lives, relative to the lang folder.
     */
    public function relativePath(string $locale, Key $key): string
    {
        if ($key->isJson()) {
            return "{$locale}.json";
        }

        if ($key->namespace === Key::APP) {
            return "{$locale}/{$key->group}.php";
        }

        return "vendor/{$key->namespace}/{$locale}/{$key->group}.php";
    }

    /**
     * @param  non-empty-list<Entry>  $entries
     */
    private function build(string $relative, array $entries): ExportedFile
    {
        $absolute = $this->catalogue->langPath().'/'.$relative;
        $before = $this->fs->isFile($absolute) ? $this->fs->get($absolute) : null;
        $key = $entries[0]->key;

        $changes = [];
        foreach ($entries as $entry) {
            $changes[$entry->key->item] = (string) $entry->value();
        }

        $order = array_keys($this->catalogue->fileLines($this->catalogue->sourceLocale(), $key->namespace, $key->group));
        $display = basename($this->catalogue->langPath()).'/'.$relative;

        if ($key->isJson()) {
            $after = JsonFile::set($before, $changes, $order);
            if ($after === null) {
                throw new ExportFailed($display, 'the file is not valid JSON. Fix it, then export again.');
            }

            return new ExportedFile($display, $absolute, $before, $after, count($changes));
        }

        if ($before !== null) {
            $after = (new PhpArrayFile($before))->set($changes, $order);
            if ($after !== null) {
                return new ExportedFile($display, $absolute, $before, $after, count($changes));
            }
        }

        // A new file, or one too clever to edit in place: write it out whole,
        // keeping the existing lines and their order.
        $lines = [];
        if ($before !== null) {
            try {
                $loaded = (static fn (string $__path) => require $__path)($absolute);
                $lines = is_array($loaded) ? $loaded : [];
            } catch (Throwable $e) {
                throw new ExportFailed($display, $e->getMessage());
            }
        } else {
            // Follow the source locale's order for a brand new file.
            $sourceOrder = array_flip($order);
            uksort($changes, fn ($a, $b) => [$sourceOrder[$a] ?? PHP_INT_MAX, $a] <=> [$sourceOrder[$b] ?? PHP_INT_MAX, $b]);
        }

        foreach ($changes as $item => $value) {
            Arr::set($lines, (string) $item, $value);
        }

        return new ExportedFile($display, $absolute, $before, PhpArrayPrinter::file($lines, $this->usesStrictTypes($key)), count($changes), rewritten: $before !== null);
    }

    private function usesStrictTypes(Key $key): bool
    {
        $source = $this->catalogue->langPath().'/'.$this->relativePath($this->catalogue->sourceLocale(), $key);

        return $this->fs->isFile($source) && str_contains($this->fs->get($source), 'strict_types=1');
    }

    /**
     * @throws ExportFailed
     */
    private function write(ExportedFile $file): void
    {
        $dir = dirname($file->absolutePath);

        try {
            $this->fs->ensureDirectoryExists($dir);
        } catch (Throwable $e) {
            throw new ExportFailed($file->path, "the folder {$dir} can't be created.");
        }

        if (! is_writable($dir) || ($this->fs->exists($file->absolutePath) && ! is_writable($file->absolutePath))) {
            throw new ExportFailed($file->path, 'it is not writable.');
        }

        // Write next to the file and move it into place, so a reader never
        // sees half a file.
        $temp = $file->absolutePath.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (file_put_contents($temp, $file->after) === false || ! rename($temp, $file->absolutePath)) {
            if (is_file($temp)) {
                unlink($temp);
            }
            throw new ExportFailed($file->path, 'writing it failed.');
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($file->absolutePath, true);
        }
    }
}
