<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Exceptions;

final class InvalidKey extends TranslationsException
{
    public function __construct(public readonly string $key, string $reason)
    {
        parent::__construct("“{$key}” can't be used as a translation key: {$reason}");
    }
}
