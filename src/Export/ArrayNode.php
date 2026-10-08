<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Export;

/**
 * @internal An array literal found by PhpArrayFile, by token positions.
 */
final class ArrayNode
{
    /**
     * @param  array<string, ArrayElement>  $elements
     * @param  list<ArrayElement>  $list
     */
    public function __construct(
        public readonly int $open,
        public readonly int $close,
        public readonly array $elements,
        public readonly array $list,
        public readonly string $path,
    ) {}
}
