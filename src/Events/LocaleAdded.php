<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a locale was added.
 */
final class LocaleAdded
{
    use Dispatchable;

    public function __construct(
        public readonly string $locale,
        public readonly ?Authenticatable $user = null,
    ) {}
}
