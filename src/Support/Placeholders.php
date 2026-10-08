<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Support;

/**
 * Compares a translation with its source text and says what it lost or
 * gained: `:name` replacements, `{count}` style placeholders, plural forms
 * separated by `|`, `{0}` / `[1,*]` ranges, and HTML tags.
 *
 * The same rules run in the browser (resources/views/partials/check.blade.php),
 * so warnings appear while typing. Keep the two in step.
 */
final class Placeholders
{
    private const COLON = '/(?<![\p{L}\p{N}_:]):([A-Za-z][A-Za-z0-9_]*)/u';

    private const BRACES = '/\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}/';

    private const RANGE = '/^\s*(\{\s*\d+\s*\}|\[\s*[\d*]+\s*,\s*[\d*]+\s*\])/';

    private const TAG = '/<([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*>/';

    /**
     * @return list<string>
     */
    public static function check(string $source, string $translation): array
    {
        $warnings = [];

        $expected = self::placeholders($source);
        $actual = self::placeholders($translation);

        foreach (array_diff_key($expected, $actual) as $spelling) {
            $warnings[] = "Missing {$spelling}, which the source text uses.";
        }
        foreach (array_diff_key($actual, $expected) as $spelling) {
            $warnings[] = "Adds {$spelling}, which the source text doesn't have.";
        }

        $sourceForms = self::forms($source);
        $forms = self::forms($translation);

        if (count($sourceForms) > 1 && count($forms) === 1) {
            $warnings[] = 'The source text has '.count($sourceForms).' plural forms separated by |; this has one.';
        } elseif (count($sourceForms) === 1 && count($forms) > 1) {
            $warnings[] = 'This has plural forms separated by |, but the source text has none.';
        } elseif (count($sourceForms) > 1) {
            $sourceRanges = self::ranges($sourceForms);
            $ranges = self::ranges($forms);
            if ($sourceRanges !== [] && $sourceRanges !== $ranges) {
                $warnings[] = 'The plural ranges differ: the source text uses '.implode(' ', $sourceRanges)
                    .($ranges === [] ? ', this uses none.' : ', this uses '.implode(' ', $ranges).'.');
            }
        }

        $sourceTags = self::tags($source);
        $tags = self::tags($translation);
        if ($sourceTags !== $tags) {
            foreach (array_diff_key($sourceTags, $tags) as $tag => $count) {
                $warnings[] = "Missing the <{$tag}> tag.";
            }
            foreach (array_diff_key($tags, $sourceTags) as $tag => $count) {
                $warnings[] = "Adds a <{$tag}> tag, which the source text doesn't have.";
            }
        }

        return $warnings;
    }

    /**
     * Every placeholder, keyed by a case-insensitive name: Laravel replaces
     * :name, :Name and :NAME from the same value.
     *
     * @return array<string, string>
     */
    public static function placeholders(string $text): array
    {
        $found = [];

        // Range markers like {0} are not placeholders.
        $body = implode("\n", array_map(fn (string $form) => preg_replace(self::RANGE, '', $form) ?? $form, self::forms($text)));

        if (preg_match_all(self::COLON, $body, $matches)) {
            foreach ($matches[1] as $name) {
                $found[':'.strtolower($name)] ??= ':'.$name;
            }
        }
        if (preg_match_all(self::BRACES, $body, $matches)) {
            foreach ($matches[1] as $name) {
                $found['{'.strtolower($name).'}'] ??= '{'.$name.'}';
            }
        }

        return $found;
    }

    /**
     * @return non-empty-list<string>
     */
    private static function forms(string $text): array
    {
        return explode('|', $text);
    }

    /**
     * @param  list<string>  $forms
     * @return list<string>
     */
    private static function ranges(array $forms): array
    {
        $ranges = [];
        foreach ($forms as $form) {
            if (preg_match(self::RANGE, $form, $match)) {
                $ranges[] = preg_replace('/\s+/', '', $match[1]) ?? $match[1];
            }
        }

        return $ranges;
    }

    /**
     * @return array<string, int>
     */
    private static function tags(string $text): array
    {
        if (! preg_match_all(self::TAG, $text, $matches)) {
            return [];
        }

        $tags = array_count_values(array_map('strtolower', $matches[1]));
        ksort($tags);

        return $tags;
    }
}
