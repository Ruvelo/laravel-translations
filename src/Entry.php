<?php

declare(strict_types=1);

namespace Ruvelo\Translations;

use Illuminate\Contracts\Support\Arrayable;
use Ruvelo\Translations\Models\Override;
use Ruvelo\Translations\Support\Placeholders;

/**
 * One translatable string in one locale: what the source locale says, what
 * the lang file says, and the override edited in the browser, if any.
 *
 * @implements Arrayable<string, mixed>
 */
final class Entry implements Arrayable
{
    public const MISSING = 'missing';

    public const CHANGED = 'changed';

    public const TRANSLATED = 'translated';

    public function __construct(
        public readonly string $locale,
        public readonly Key $key,
        public readonly ?string $source,
        public readonly ?string $file,
        public readonly ?Override $override = null,
    ) {}

    /**
     * The text the app shows: the override if there is one, else the file.
     */
    public function value(): ?string
    {
        return $this->override !== null ? $this->override->value : $this->file;
    }

    public function isMissing(): bool
    {
        $value = $this->value();

        return $value === null || trim($value) === '';
    }

    /**
     * Edited in the browser and not yet in the lang file.
     */
    public function isPending(): bool
    {
        return $this->override !== null && $this->override->value !== $this->file;
    }

    /**
     * The lang file changed after this override was made: someone may have
     * fixed the same string in the code.
     */
    public function fileChangedSinceEdit(): bool
    {
        return $this->isPending() && $this->override?->file_value !== $this->file;
    }

    public function status(): string
    {
        return match (true) {
            $this->isMissing() => self::MISSING,
            $this->isPending() => self::CHANGED,
            default => self::TRANSLATED,
        };
    }

    /**
     * Placeholders and plural forms the translation dropped or added,
     * compared with the source text.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $value = $this->value();

        if ($this->source === null || $value === null || trim($value) === '') {
            return [];
        }

        return Placeholders::check($this->source, $value);
    }

    public function matches(string $search): bool
    {
        $search = trim($search);

        if ($search === '') {
            return true;
        }

        foreach ([$this->key->full(), $this->source, $this->value()] as $haystack) {
            if ($haystack !== null && mb_stripos($haystack, $search) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * A stable id for HTML: rows, labels and anchors.
     */
    public function id(): string
    {
        return 't'.substr($this->key->hash(), 0, 12);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'key' => $this->key->full(),
            'namespace' => $this->key->namespace,
            'group' => $this->key->group,
            'item' => $this->key->item,
            'source' => $this->source,
            'value' => $this->value(),
            'file_value' => $this->file,
            'status' => $this->status(),
            'pending' => $this->isPending(),
            'file_changed' => $this->fileChangedSinceEdit(),
            'warnings' => $this->warnings(),
            'updated_at' => $this->override?->updated_at->toIso8601String(),
        ];
    }
}
