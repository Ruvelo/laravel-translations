<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Export;

/**
 * Sets keys in a locale's JSON file, keeping its order, its indentation and
 * its final newline. New keys go after their neighbour in the source locale.
 */
final class JsonFile
{
    /**
     * @param  array<string, string>  $changes
     * @param  list<string>  $order  The source locale's keys, in order
     */
    public static function set(?string $contents, array $changes, array $order = []): ?string
    {
        $lines = [];

        if ($contents !== null && trim($contents) !== '') {
            $decoded = json_decode($contents, true);
            if (! is_array($decoded)) {
                return null;
            }
            $lines = $decoded;
        }

        foreach ($changes as $key => $value) {
            if (array_key_exists($key, $lines)) {
                $lines[$key] = $value;

                continue;
            }

            $lines = self::insert($lines, (string) $key, $value, $order);
        }

        $indent = '    ';
        if ($contents !== null && preg_match('/^\{\s*\n([ \t]+)"/', $contents, $m) === 1) {
            $indent = $m[1];
        }

        $json = $lines === [] ? '{}' : (string) json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);

        if ($indent !== '    ') {
            $json = (string) preg_replace_callback('/^((?:    )+)/m', fn (array $m) => str_repeat($indent, intdiv(strlen($m[1]), 4)), $json);
        }

        $newline = $contents === null || $contents === '' || str_ends_with($contents, "\n");

        return $json.($newline ? "\n" : '');
    }

    /**
     * @param  array<array-key, mixed>  $lines
     * @param  list<string>  $order
     * @return array<array-key, mixed>
     */
    private static function insert(array $lines, string $key, string $value, array $order): array
    {
        $index = array_search($key, $order, true);

        if ($index !== false) {
            for ($i = $index - 1; $i >= 0; $i--) {
                if (array_key_exists($order[$i], $lines)) {
                    $position = array_search($order[$i], array_map('strval', array_keys($lines)), true);
                    if ($position !== false) {
                        return array_slice($lines, 0, $position + 1, true) + [$key => $value] + array_slice($lines, $position + 1, null, true);
                    }
                }
            }

            if ($lines !== []) {
                return [$key => $value] + $lines;
            }
        }

        $lines[$key] = $value;

        return $lines;
    }
}
