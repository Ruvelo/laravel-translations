<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Exceptions;

final class LocaleAlreadyExists extends TranslationsException
{
    public function __construct(public readonly string $locale)
    {
        parent::__construct("The “{$locale}” locale already exists.");
    }
}
