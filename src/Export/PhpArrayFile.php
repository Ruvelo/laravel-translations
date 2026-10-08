<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Export;

use PhpToken;

/**
 * Edits a lang file's `return [...]` in place: changed strings are swapped
 * where they stand and new keys slot in next to their neighbours, so
 * comments, quotes, alignment and key order survive an export.
 *
 * When the file is something this can't follow (built with functions or
 * spread operators, say), set() returns null and the exporter rewrites the
 * file instead.
 */
final class PhpArrayFile
{
    /** @var list<PhpToken> */
    private array $tokens = [];

    private string $unit = '    ';

    public function __construct(private readonly string $code) {}

    /**
     * @param  array<string, string>  $changes  Dot keys to new values
     * @param  list<string>  $order  The source locale's dot keys, in order, to place new keys
     */
    public function set(array $changes, array $order = []): ?string
    {
        $this->tokens = array_values(PhpToken::tokenize($this->code));
        $root = $this->root();

        if ($root === null) {
            return null;
        }

        $this->unit = $this->detectUnit($root);

        /** @var list<array{0: int, 1: int, 2: string, 3: int}> $edits [offset, length, text, sequence] */
        $edits = [];
        /** @var array<int, array{node: ArrayNode, keys: array<string, array<string, string>>}> $inserts */
        $inserts = [];
        /** @var array<string, array{element: ArrayElement, lines: array<string, string>}> $converts */
        $converts = [];

        foreach ($changes as $path => $value) {
            $segments = explode('.', (string) $path);
            $node = $root;

            foreach ($segments as $i => $segment) {
                $element = $node->elements[$segment] ?? null;
                $rest = implode('.', array_slice($segments, $i + 1));

                if ($element === null) {
                    $inserts[$node->open] ??= ['node' => $node, 'keys' => []];
                    $inserts[$node->open]['keys'][$segment] ??= [];
                    $inserts[$node->open]['keys'][$segment][$rest] = $value;
                    break;
                }

                if ($rest === '') {
                    $edits[] = [...$this->range($element->valueStart, $element->valueEnd), PhpArrayPrinter::literal($value), 0];
                    break;
                }

                if ($element->node !== null) {
                    $node = $element->node;

                    continue;
                }

                // A string where the new key needs an array: the array wins,
                // as it does at runtime.
                $id = $element->valueStart.':'.$element->valueEnd;
                $converts[$id] ??= ['element' => $element, 'lines' => []];
                $converts[$id]['lines'][$rest] = $value;
                break;
            }
        }

        foreach ($converts as $convert) {
            $indent = $this->indentOf($convert['element']->keyStart);
            $edits[] = [...$this->range($convert['element']->valueStart, $convert['element']->valueEnd),
                PhpArrayPrinter::array(PhpArrayPrinter::nest($convert['lines']), $indent, $this->unit), 0];
        }

        foreach ($inserts as $insert) {
            foreach ($this->insertEdits($insert['node'], $insert['keys'], $order) as $edit) {
                $edits[] = $edit;
            }
        }

        // Apply from the end of the file backwards, so offsets stay valid.
        usort($edits, fn (array $a, array $b) => [$b[0], $b[3]] <=> [$a[0], $a[3]]);

        $code = $this->code;
        foreach ($edits as [$offset, $length, $text]) {
            $code = substr_replace($code, $text, $offset, $length);
        }

        return $code;
    }

    /**
     * @param  array<string, array<string, string>>  $keys  New top-level key => [rest of dot key => value]
     * @param  list<string>  $order
     * @return list<array{0: int, 1: int, 2: string, 3: int}>
     */
    private function insertEdits(ArrayNode $node, array $keys, array $order): array
    {
        $elements = $node->elements;
        $list = $node->list;

        // Siblings at this level, in the source locale's order.
        $prefix = $node->path === '' ? '' : $node->path.'.';
        $siblings = [];
        foreach ($order as $path) {
            if ($prefix === '' || str_starts_with($path, $prefix)) {
                $name = explode('.', substr($path, strlen($prefix)))[0];
                $siblings[$name] = true;
            }
        }
        $siblings = array_keys($siblings);

        // Each new key goes after the nearest earlier sibling that exists.
        $groups = [];
        $sorted = array_keys($keys);
        usort($sorted, function (string $a, string $b) use ($siblings) {
            $ia = array_search($a, $siblings, true);
            $ib = array_search($b, $siblings, true);

            return [$ia === false ? PHP_INT_MAX : $ia] <=> [$ib === false ? PHP_INT_MAX : $ib];
        });

        foreach ($sorted as $name) {
            $anchor = '@end';
            $index = array_search($name, $siblings, true);
            if ($index !== false) {
                $anchor = '@start';
                for ($i = $index - 1; $i >= 0; $i--) {
                    if (isset($elements[$siblings[$i]])) {
                        $anchor = $siblings[$i];
                        break;
                    }
                }
                if ($anchor === '@start' && $list === []) {
                    $anchor = '@end';
                }
            }
            $groups[$anchor][] = $name;
        }

        $multiline = $list === [] || str_contains($this->between($node->open, $list[0]->keyStart), "\n");
        $baseIndent = $this->indentOf($node->open);
        $indent = $list !== [] && $multiline ? $this->indentOf($list[0]->keyStart) : $baseIndent.$this->unit;

        $edits = [];
        foreach ($groups as $anchor => $names) {
            $text = '';
            foreach ($names as $name) {
                $rest = $keys[$name];
                $value = (count($rest) === 1 && array_key_first($rest) === '')
                    ? PhpArrayPrinter::literal((string) $rest[''])
                    : PhpArrayPrinter::array(PhpArrayPrinter::nest($rest), $indent, $this->unit);
                $text .= ($multiline ? "\n".$indent : ' ').PhpArrayPrinter::key($name).' => '.$value.',';
            }

            if ($list === []) {
                // An empty array: open it up.
                $text = rtrim($text, ',').($multiline ? ",\n".$baseIndent : ' ');
                [$offset, $length] = $this->range($node->open, $node->close);
                $open = $this->tokens[$node->open]->text === '[' ? '[' : 'array(';
                $close = $open === '[' ? ']' : ')';
                $edits[] = [$offset, $length, $open.$text.$close, 1];

                continue;
            }

            if ($anchor === '@start') {
                $offset = $this->end($node->open);
                $edits[] = [$offset, 0, $multiline ? $text : ltrim($text).' ', 1];

                continue;
            }

            $element = $anchor === '@end' ? $list[count($list) - 1] : $elements[$anchor];

            if ($element->comma !== null) {
                $edits[] = [$this->end($element->comma), 0, $text, 1];
            } else {
                // The last element had no trailing comma; keep that style.
                $edits[] = [$this->end($element->valueEnd), 0, ','.rtrim($text, ','), 1];
            }
        }

        return $edits;
    }

    private function root(): ?ArrayNode
    {
        $count = count($this->tokens);
        $depth = 0;

        for ($i = 0; $i < $count; $i++) {
            $token = $this->tokens[$i];

            if ($token->is(['{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif ($token->is('}')) {
                $depth--;
            } elseif ($depth === 0 && $token->is(T_RETURN)) {
                $next = $this->next($i);

                return $next === null ? null : $this->parseArray($next, '');
            }
        }

        return null;
    }

    private function parseArray(int $i, string $path): ?ArrayNode
    {
        $token = $this->tokens[$i];

        if ($token->is(T_ARRAY)) {
            $i = $this->next($i);
            if ($i === null || ! $this->tokens[$i]->is('(')) {
                return null;
            }
            $close = ')';
        } elseif ($token->is('[')) {
            $close = ']';
        } else {
            return null;
        }

        $open = $i;
        $elements = [];
        $list = [];
        $j = $this->next($i);

        while ($j !== null && ! $this->tokens[$j]->is($close)) {
            $keyStart = $j;
            $arrow = $this->scan($j, ['=>']);
            if ($arrow === null || ! $this->tokens[$arrow]->is(T_DOUBLE_ARROW)) {
                return null; // A list or a spread: not a lang array.
            }

            $keyToken = $this->tokens[$keyStart];
            if ($this->next($keyStart) !== $arrow || ! $keyToken->is([T_CONSTANT_ENCAPSED_STRING, T_LNUMBER])) {
                return null; // Computed keys.
            }
            $key = $keyToken->is(T_LNUMBER) ? $keyToken->text : self::unquote($keyToken->text);

            $valueStart = $this->next($arrow);
            if ($valueStart === null) {
                return null;
            }

            $node = null;
            $valueToken = $this->tokens[$valueStart];
            $childPath = $path === '' ? $key : $path.'.'.$key;

            if ($valueToken->is('[') || ($valueToken->is(T_ARRAY) && ($n = $this->next($valueStart)) !== null && $this->tokens[$n]->is('('))) {
                $node = $this->parseArray($valueStart, $childPath);
                if ($node === null) {
                    return null;
                }
                $valueEnd = $node->close;
                $after = $this->next($valueEnd);
            } else {
                $stop = $this->scan($valueStart, [',', $close]);
                if ($stop === null) {
                    return null;
                }
                $valueEnd = $this->previous($stop);
                $after = $stop;
            }

            if ($after === null) {
                return null;
            }

            $comma = null;
            if ($this->tokens[$after]->is(',')) {
                $comma = $after;
                $j = $this->next($after);
            } elseif ($this->tokens[$after]->is($close)) {
                $j = $after;
            } else {
                return null;
            }

            $element = new ArrayElement($key, $keyStart, $valueStart, $valueEnd, $comma, $node);
            $elements[$key] = $element;
            $list[] = $element;
        }

        if ($j === null) {
            return null;
        }

        return new ArrayNode($open, $j, $elements, $list, $path);
    }

    /**
     * The index of the first token at depth 0 from $i that is one of $stops
     * ('=>' for the double arrow, or single characters).
     *
     * @param  list<string>  $stops
     */
    private function scan(int $i, array $stops): ?int
    {
        $depth = 0;
        $count = count($this->tokens);

        for (; $i < $count; $i++) {
            $token = $this->tokens[$i];

            if ($depth === 0) {
                if (in_array('=>', $stops, true) && $token->is(T_DOUBLE_ARROW)) {
                    return $i;
                }
                foreach ($stops as $stop) {
                    if ($stop !== '=>' && $token->is($stop)) {
                        return $i;
                    }
                }
                if ($token->is([']', ')', '}'])) {
                    return $i; // The enclosing array closed first.
                }
            }

            if ($token->is(['[', '(', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif ($token->is([']', ')', '}'])) {
                $depth--;
            }
        }

        return null;
    }

    private function next(int $i): ?int
    {
        $count = count($this->tokens);
        for ($i++; $i < $count; $i++) {
            if (! $this->tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    private function previous(int $i): int
    {
        for ($i--; $i > 0; $i--) {
            if (! $this->tokens[$i]->isIgnorable()) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * Byte offset and length from the start of one token to the end of another.
     *
     * @return array{0: int, 1: int}
     */
    private function range(int $from, int $to): array
    {
        $start = $this->tokens[$from]->pos;

        return [$start, $this->end($to) - $start];
    }

    private function end(int $i): int
    {
        return $this->tokens[$i]->pos + strlen($this->tokens[$i]->text);
    }

    private function between(int $from, int $to): string
    {
        $start = $this->end($from);

        return substr($this->code, $start, $this->tokens[$to]->pos - $start);
    }

    /**
     * The indentation of the line a token sits on.
     */
    private function indentOf(int $i): string
    {
        $pos = $this->tokens[$i]->pos;
        $lineStart = strrpos(substr($this->code, 0, $pos), "\n");
        $line = substr($this->code, $lineStart === false ? 0 : $lineStart + 1);

        return (string) (preg_match('/^[ \t]*/', $line, $m) ? $m[0] : '');
    }

    private function detectUnit(ArrayNode $root): string
    {
        $list = $root->list;

        if ($list !== []) {
            $indent = $this->indentOf($list[0]->keyStart);
            $base = $this->indentOf($root->open);
            if (strlen($indent) > strlen($base) && str_starts_with($indent, $base)) {
                return substr($indent, strlen($base));
            }
        }

        return '    ';
    }

    public static function unquote(string $literal): string
    {
        $quote = $literal[0] ?? '';
        $body = substr($literal, 1, -1);

        if ($quote === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $body);
        }

        return stripcslashes($body);
    }
}
