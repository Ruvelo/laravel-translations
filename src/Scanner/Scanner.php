<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Scanner;

use Illuminate\Filesystem\Filesystem;
use Ruvelo\Translations\Entry;
use Ruvelo\Translations\Exceptions\InvalidKey;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Support\Catalogue;

/**
 * Finds the translation keys your code uses, and compares them with the
 * source locale: what's missing, and what nothing seems to use.
 *
 * Only literal keys are found: __('billing.title') yes, __($key) no. Keys
 * built at runtime (__('status.'.$name)) count as using every key that
 * starts with the literal part, so they're never reported as unused.
 */
class Scanner
{
    private const PHP_CALL = <<<'REGEX'
        /(?:(?<![\w$>:\\])(?:__|trans|trans_choice)|(?<![\w$>])\\?(?:Illuminate\\Support\\Facades\\)?Lang::(?:get|choice)|@(?:lang|choice))\s*\(\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\$]|\\.)*)")\s*(?=[,)])/s
        REGEX;

    private const PHP_PREFIX = <<<'REGEX'
        /(?:(?<![\w$>:\\])(?:__|trans|trans_choice)|(?<![\w$>])\\?(?:Illuminate\\Support\\Facades\\)?Lang::(?:get|choice)|@(?:lang|choice))\s*\(\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\$]|\\.)*)")\s*\./s
        REGEX;

    public function __construct(
        private readonly Catalogue $catalogue,
        private readonly Filesystem $fs,
    ) {}

    /**
     * @param  list<string>|null  $paths  Folders or files, relative to the app root; null uses config
     * @param  bool|null  $js  Also read JS/TS/Vue files; null uses config
     */
    public function scan(?array $paths = null, ?bool $js = null): ScanResult
    {
        $js ??= (bool) config('translations.scan.js', false);
        $paths ??= array_values(array_filter((array) config('translations.scan.paths', []), 'is_string'));

        if ($js) {
            $paths = [...$paths, ...array_values(array_filter((array) config('translations.scan.js_paths', []), 'is_string'))];
        }

        $used = [];
        $prefixes = [];
        $count = 0;

        foreach ($this->files($paths, $js) as $file) {
            $count++;
            $code = $this->fs->get($file);
            $isJs = ! str_ends_with($file, '.php');
            $relative = ltrim(str_replace(base_path(), '', $file), '/');

            foreach ($this->find($code, $isJs) as [$key, $offset]) {
                $used[$key][] = $relative.':'.(substr_count(substr($code, 0, $offset), "\n") + 1);
            }

            foreach ($this->findPrefixes($code, $isJs) as $prefix) {
                $prefixes[$prefix] = true;
            }
        }

        ksort($used);
        $prefixes = array_keys($prefixes);

        return new ScanResult($used, $this->missing(array_keys($used)), $this->unused(array_keys($used), $prefixes), $prefixes, $count);
    }

    /**
     * Keys in one file's code, with their byte offsets.
     *
     * @return list<array{0: string, 1: int}>
     */
    public function find(string $code, bool $js = false): array
    {
        $pattern = $js ? $this->jsPattern(false) : trim(self::PHP_CALL);
        if ($pattern === null || ! preg_match_all($pattern, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL)) {
            return [];
        }

        $found = [];
        foreach ($matches as $match) {
            $key = $this->unescape($match);
            if ($key !== null && trim($key) !== '') {
                $found[] = [$key, (int) $match[0][1]];
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function findPrefixes(string $code, bool $js): array
    {
        $pattern = $js ? $this->jsPattern(true) : trim(self::PHP_PREFIX);
        if ($pattern === null || ! preg_match_all($pattern, $code, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL)) {
            return [];
        }

        $prefixes = [];
        foreach ($matches as $match) {
            $prefix = $this->unescape($match);
            if ($prefix !== null && $prefix !== '') {
                $prefixes[] = $prefix;
            }
        }

        return $prefixes;
    }

    /**
     * @param  array<int|string, array{0: string|null, 1: int}>  $match
     */
    private function unescape(array $match): ?string
    {
        if (($single = $match[1][0] ?? null) !== null) {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $single);
        }

        if (($double = $match[2][0] ?? null) !== null) {
            return stripcslashes($double);
        }

        if (($template = $match[3][0] ?? null) !== null) {
            return str_contains($template, '${') ? null : $template;
        }

        return null;
    }

    /**
     * The configured JS functions: $t('key'), t("key"), i18n.t(`key`).
     */
    private function jsPattern(bool $prefix): ?string
    {
        $functions = array_values(array_filter((array) config('translations.scan.js_functions', []), 'is_string'));
        if ($functions === []) {
            return null;
        }

        usort($functions, fn (string $a, string $b) => strlen($b) <=> strlen($a));
        $names = implode('|', array_map(fn (string $name) => preg_quote($name, '/'), $functions));
        $tail = $prefix ? '\s*\+' : '\s*(?=[,)])';

        return '/(?<![\w$.])(?:'.$names.')\s*\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)"|`((?:[^`\\\\]|\\\\.)*)`)'.$tail.'/s';
    }

    /**
     * @param  list<string>  $keys
     * @return list<Key>
     */
    private function missing(array $keys): array
    {
        $source = $this->catalogue->sourceLocale();
        $groups = array_map(fn (array $file) => $file[1], array_filter($this->catalogue->groups(), fn (array $file) => $file[0] === Key::APP));
        $missing = [];

        foreach ($keys as $string) {
            // __('billing') returns the whole file: it exists if the file does.
            if (! str_contains($string, ' ') && ! str_contains($string, '.') && in_array($string, $groups, true)) {
                continue;
            }

            try {
                $key = $this->catalogue->resolve($string, $source);
            } catch (InvalidKey) {
                continue;
            }

            if ($this->catalogue->effective($source, $key) !== null || $this->isSubtree($source, $key)) {
                continue;
            }

            $missing[] = $key;
        }

        return $missing;
    }

    /**
     * __('billing.plans') with nested keys under plans returns an array.
     */
    private function isSubtree(string $locale, Key $key): bool
    {
        if ($key->isJson()) {
            return false;
        }

        foreach (array_keys($this->catalogue->fileLines($locale, $key->namespace, $key->group)) as $item) {
            if (str_starts_with((string) $item, $key->item.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $keys
     * @param  list<string>  $prefixes
     * @return list<Key>
     */
    private function unused(array $keys, array $prefixes): array
    {
        $used = array_flip($keys);
        $framework = array_map('strval', (array) config('translations.scan.framework_groups', []));
        $unused = [];

        foreach ($this->catalogue->entries($this->catalogue->sourceLocale()) as $entry) {
            /** @var Entry $entry */
            $key = $entry->key;

            if ($key->namespace !== Key::APP || in_array($key->group, $framework, true)) {
                continue;
            }

            $full = $key->full();
            if (isset($used[$full]) || isset($used[$key->group])) {
                continue;
            }

            foreach ([...$prefixes, ...$keys] as $prefix) {
                if (str_starts_with($full, $prefix) && ($prefix !== $full) && (in_array($prefix, $prefixes, true) || str_starts_with($full, $prefix.'.'))) {
                    continue 2;
                }
            }

            $unused[] = $key;
        }

        return $unused;
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function files(array $paths, bool $js): array
    {
        $extensions = $js ? ['php', 'js', 'ts', 'vue', 'jsx', 'tsx', 'mjs', 'svelte'] : ['php'];
        $files = [];

        foreach ($paths as $path) {
            $absolute = str_starts_with($path, '/') ? $path : base_path($path);

            if ($this->fs->isFile($absolute)) {
                $files[] = $absolute;

                continue;
            }

            if (! $this->fs->isDirectory($absolute)) {
                continue;
            }

            foreach ($this->fs->allFiles($absolute) as $file) {
                if (in_array(strtolower($file->getExtension()), $extensions, true) && ! str_contains($file->getPathname(), '/node_modules/')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }
}
