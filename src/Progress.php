<?php

declare(strict_types=1);

namespace Ruvelo\Translations;

use Illuminate\Contracts\Support\Arrayable;

/**
 * How far along a locale is, measured against the source locale's keys.
 *
 * @implements Arrayable<string, int|string|bool>
 */
final class Progress implements Arrayable
{
    public function __construct(
        public readonly string $locale,
        public readonly int $total,
        public readonly int $translated,
        public readonly int $pending,
        public readonly bool $isSource = false,
    ) {}

    public function missing(): int
    {
        return max(0, $this->total - $this->translated);
    }

    /**
     * Rounded down, so 100 means every string is translated.
     */
    public function percent(): int
    {
        if ($this->total === 0) {
            return 100;
        }

        return (int) floor($this->translated * 100 / $this->total);
    }

    public function isComplete(): bool
    {
        return $this->missing() === 0;
    }

    /**
     * @return array{locale: string, total: int, translated: int, missing: int, percent: int, pending: int, source: bool}
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'total' => $this->total,
            'translated' => $this->translated,
            'missing' => $this->missing(),
            'percent' => $this->percent(),
            'pending' => $this->pending,
            'source' => $this->isSource,
        ];
    }
}
