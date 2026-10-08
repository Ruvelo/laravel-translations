<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Export;

use Ruvelo\Translations\Support\Diff;

/**
 * One lang file an export wrote (or would write).
 */
final class ExportedFile
{
    /**
     * @param  string  $path  Relative to the lang folder's parent, e.g. "lang/fr/billing.php"
     * @param  string|null  $before  null when the file is new
     * @param  bool  $rewritten  The file couldn't be edited in place and was written out afresh
     */
    public function __construct(
        public readonly string $path,
        public readonly string $absolutePath,
        public readonly ?string $before,
        public readonly string $after,
        public readonly int $changes,
        public readonly bool $rewritten = false,
    ) {}

    public function isNew(): bool
    {
        return $this->before === null;
    }

    /**
     * A unified diff, for --dry-run.
     */
    public function diff(int $context = 2): string
    {
        $ops = Diff::collapse(Diff::lines($this->before ?? '', $this->after), $context);
        $out = ['--- '.($this->before === null ? '/dev/null' : 'a/'.$this->path), '+++ b/'.$this->path];

        foreach ($ops as [$op, $line]) {
            $out[] = $op === '…' ? '@@ '.$line.' unchanged line'.($line === 1 ? '' : 's').' @@' : $op.$line;
        }

        return implode("\n", $out);
    }
}
