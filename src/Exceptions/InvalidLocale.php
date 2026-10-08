<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Exceptions;

final class InvalidLocale extends TranslationsException
{
    public function __construct(public readonly string $locale, string $message = '')
    {
        parent::__construct($message !== '' ? $message : "“{$locale}” isn't a locale code. Use letters like fr, pt_BR or zh-Hant.");
    }

    public static function unknown(string $locale): self
    {
        return new self($locale, "There is no “{$locale}” locale. Add it first.");
    }
}
