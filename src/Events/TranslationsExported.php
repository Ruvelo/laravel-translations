<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Translations\Export\ExportResult;

/**
 * Fired after pending changes were written into the lang files.
 */
final class TranslationsExported
{
    use Dispatchable;

    public function __construct(public readonly ExportResult $result) {}
}
