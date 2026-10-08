<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Exceptions;

final class SuggestionsUnavailable extends TranslationsException
{
    public function __construct(string $reason = 'Suggestions need the Laravel AI SDK (laravel/ai) installed and configured.')
    {
        parent::__construct($reason);
    }
}
