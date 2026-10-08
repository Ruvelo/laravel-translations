<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Support;

final class Diff
{
    /**
     * Above this many cells the LCS table costs too much memory; the changed
     * middle is shown as a block replacement instead.
     */
    private const MAX_CELLS = 1_000_000;

    /**
     * A line diff: each entry is [op, line] where op is ' ', '-' or '+'.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function lines(string $old, string $new): array
    {
        $a = self::split($old);
        $b = self::split($new);

        // Trim the shared head and tail so the LCS only covers what changed.
        $start = 0;
        while ($start < count($a) && $start < count($b) && $a[$start] === $b[$start]) {
            $start++;
        }

        $endA = count($a);
        $endB = count($b);
        while ($endA > $start && $endB > $start && $a[$endA - 1] === $b[$endB - 1]) {
            $endA--;
            $endB--;
        }

        $ops = [];
        foreach (array_slice($a, 0, $start) as $line) {
            $ops[] = [' ', $line];
        }

        $x = array_slice($a, $start, $endA - $start);
        $y = array_slice($b, $start, $endB - $start);
        $p = count($x);
        $q = count($y);

        if ($p * $q > self::MAX_CELLS) {
            foreach ($x as $line) {
                $ops[] = ['-', $line];
            }
            foreach ($y as $line) {
                $ops[] = ['+', $line];
            }
        } else {
            $lcs = array_fill(0, $p + 1, array_fill(0, $q + 1, 0));
            for ($i = $p - 1; $i >= 0; $i--) {
                for ($j = $q - 1; $j >= 0; $j--) {
                    $lcs[$i][$j] = $x[$i] === $y[$j]
                        ? $lcs[$i + 1][$j + 1] + 1
                        : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
                }
            }

            $i = $j = 0;
            while ($i < $p && $j < $q) {
                if ($x[$i] === $y[$j]) {
                    $ops[] = [' ', $x[$i]];
                    $i++;
                    $j++;
                } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                    $ops[] = ['-', $x[$i++]];
                } else {
                    $ops[] = ['+', $y[$j++]];
                }
            }
            while ($i < $p) {
                $ops[] = ['-', $x[$i++]];
            }
            while ($j < $q) {
                $ops[] = ['+', $y[$j++]];
            }
        }

        foreach (array_slice($a, $endA) as $line) {
            $ops[] = [' ', $line];
        }

        return $ops;
    }

    /**
     * Keeps `$context` unchanged lines around each change and replaces longer
     * unchanged stretches with a ['…', count] marker.
     *
     * @param  list<array{0: string, 1: string}>  $ops
     * @return list<array{0: string, 1: string|int}>
     */
    public static function collapse(array $ops, int $context = 3): array
    {
        $changed = array_keys(array_filter($ops, fn (array $op) => $op[0] !== ' '));
        $keep = [];
        foreach ($changed as $i) {
            for ($j = max(0, $i - $context); $j <= min(count($ops) - 1, $i + $context); $j++) {
                $keep[$j] = true;
            }
        }

        $out = [];
        $skipped = 0;
        foreach ($ops as $i => $op) {
            if (isset($keep[$i])) {
                if ($skipped > 0) {
                    $out[] = ['…', $skipped];
                    $skipped = 0;
                }
                $out[] = $op;
            } else {
                $skipped++;
            }
        }
        if ($skipped > 0 && $out !== []) {
            $out[] = ['…', $skipped];
        }

        return $out;
    }

    /**
     * @param  list<array{0: string, 1: string|int}>  $ops
     * @return array{added: int, removed: int}
     */
    public static function stats(array $ops): array
    {
        $counts = array_count_values(array_column($ops, 0));

        return ['added' => $counts['+'] ?? 0, 'removed' => $counts['-'] ?? 0];
    }

    /**
     * @return list<string>
     */
    private static function split(string $text): array
    {
        return $text === '' ? [] : (preg_split('/\R/u', $text) ?: [$text]);
    }
}
