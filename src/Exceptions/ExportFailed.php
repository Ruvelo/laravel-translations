<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Exceptions;

final class ExportFailed extends TranslationsException
{
    public function __construct(public readonly string $path, string $reason)
    {
        parent::__construct("Couldn't write {$path}: {$reason}");
    }
}
