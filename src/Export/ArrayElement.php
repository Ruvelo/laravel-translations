<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Export;

/**
 * @internal One `'key' => value` pair found by PhpArrayFile.
 */
final class ArrayElement
{
    public function __construct(
        public readonly string $key,
        public readonly int $keyStart,
        public readonly int $valueStart,
        public readonly int $valueEnd,
        public readonly ?int $comma,
        public readonly ?ArrayNode $node,
    ) {}
}
