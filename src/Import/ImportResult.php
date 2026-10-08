<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Import;

use Ruvelo\Translations\Key;

/**
 * What an import changed.
 */
final class ImportResult
{
    /**
     * @param  list<array{locale: string, key: Key}>  $imported  Now pending, ready to review and export
     * @param  int  $unchanged  Already said the same
     * @param  list<string>  $skipped  Why some lines were left out
     */
    public function __construct(
        public readonly array $imported,
        public readonly int $unchanged,
        public readonly array $skipped = [],
    ) {}

    public function count(): int
    {
        return count($this->imported);
    }
}
