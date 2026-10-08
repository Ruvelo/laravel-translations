<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Support;

/**
 * The translation keys used while rendering the current request, for the
 * "Translations on this page" panel.
 */
final class KeyRecorder
{
    /** @var array<string, true> */
    private array $keys = [];

    private int $paused = 0;

    public function record(string $key): void
    {
        if ($this->paused > 0 || isset($this->keys[$key])) {
            return;
        }

        // Laravel probes these while building validation messages; they are
        // lookups, not text on the page.
        if (str_starts_with($key, 'validation.custom.') || str_starts_with($key, 'validation.attributes.') || $key === '') {
            return;
        }

        if (count($this->keys) < max(1, (int) config('translations.in_context.max_keys', 300))) {
            $this->keys[$key] = true;
        }
    }

    /**
     * Run the callback without recording, e.g. while rendering the panel.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function paused(callable $callback): mixed
    {
        $this->paused++;

        try {
            return $callback();
        } finally {
            $this->paused--;
        }
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->keys);
    }

    public function flush(): void
    {
        $this->keys = [];
    }
}
