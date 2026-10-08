<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Export;

use Ruvelo\Translations\Entry;

/**
 * What an export wrote, or would write on a dry run.
 */
final class ExportResult
{
    /**
     * @param  list<ExportedFile>  $files
     * @param  list<Entry>  $entries  The changes written
     */
    public function __construct(
        public readonly array $files,
        public readonly array $entries,
        public readonly bool $dryRun,
        public readonly int $pruned = 0,
    ) {}

    public function count(): int
    {
        return count($this->entries);
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_values(array_unique(array_map(fn (Entry $entry) => $entry->locale, $this->entries)));
    }
}
