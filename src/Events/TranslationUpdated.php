<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Ruvelo\Translations\Key;

/**
 * Fired after a translation is edited, or reverted to its lang file.
 */
final class TranslationUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $locale,
        public readonly Key $key,
        public readonly ?string $previous,
        public readonly ?string $value,
        public readonly ?Authenticatable $user = null,
        public readonly bool $reverted = false,
    ) {}

    public function wasReverted(): bool
    {
        return $this->reverted;
    }
}
