<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Export;

/**
 * Writes PHP arrays the way Laravel's own lang files look: short arrays,
 * single quotes, four-space indents and trailing commas.
 */
final class PhpArrayPrinter
{
    /**
     * @param  array<array-key, mixed>  $lines
     */
    public static function file(array $lines, bool $strictTypes = false): string
    {
        return "<?php\n\n".($strictTypes ? "declare(strict_types=1);\n\n" : '').'return '.self::array($lines, '', '    ').";\n";
    }

    /**
     * @param  array<array-key, mixed>  $lines
     */
    public static function array(array $lines, string $indent, string $unit): string
    {
        if ($lines === []) {
            return '[]';
        }

        $out = "[\n";
        foreach ($lines as $key => $value) {
            $out .= $indent.$unit.self::key($key).' => '
                .(is_array($value) ? self::array($value, $indent.$unit, $unit) : self::literal(is_scalar($value) ? (string) $value : ''))
                .",\n";
        }

        return $out.$indent.']';
    }

    public static function literal(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    public static function key(int|string $key): string
    {
        return is_int($key) ? (string) $key : self::literal($key);
    }

    /**
     * Turn dotted keys into the nested array they stand for, keeping order.
     *
     * @param  array<string, string>  $flat
     * @return array<string, mixed>
     */
    public static function nest(array $flat): array
    {
        $tree = [];

        foreach ($flat as $path => $value) {
            $node = &$tree;
            $segments = explode('.', (string) $path);
            $last = array_pop($segments);

            foreach ($segments as $segment) {
                if (! isset($node[$segment]) || ! is_array($node[$segment])) {
                    $node[$segment] = [];
                }
                $node = &$node[$segment];
            }

            $node[$last] = $value;
            unset($node);
        }

        return $tree;
    }
}
