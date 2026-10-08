<?php

declare(strict_types=1);

namespace Ruvelo\Translations;

use Ruvelo\Translations\Exceptions\InvalidKey;
use Stringable;

/**
 * Where a translation lives: a key in a PHP file (optionally a package's),
 * or a key in the locale's JSON file.
 *
 *     Key::group('billing', 'invoice.title')      // __('billing.invoice.title')
 *     Key::group('messages', 'hello', 'courier')  // __('courier::messages.hello')
 *     Key::json('Pay now')                        // __('Pay now')
 */
final class Key implements Stringable
{
    public const JSON = '*';

    public const APP = '*';

    private function __construct(
        public readonly string $namespace,
        public readonly string $group,
        public readonly string $item,
    ) {}

    /**
     * @throws InvalidKey
     */
    public static function group(string $group, string $item, ?string $namespace = null): self
    {
        $namespace = $namespace === null || $namespace === '' ? self::APP : $namespace;
        $full = ($namespace === self::APP ? '' : $namespace.'::').$group.'.'.$item;

        if ($namespace !== self::APP && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.\-]{0,99}$/', $namespace) !== 1) {
            throw new InvalidKey($full, 'package names may use letters, numbers, dots, dashes and underscores.');
        }
        if (preg_match('/^[A-Za-z0-9_\-]+(\/[A-Za-z0-9_\-]+)*$/', $group) !== 1 || strlen($group) > 150) {
            throw new InvalidKey($full, 'file names may use letters, numbers, dashes, underscores and slashes.');
        }
        if ($item === '' || str_starts_with($item, '.') || str_ends_with($item, '.') || str_contains($item, '..')) {
            throw new InvalidKey($full, 'it needs a name after the file name, without empty parts between dots.');
        }
        if (mb_strlen($item) > 255) {
            throw new InvalidKey($full, 'keys in PHP files are limited to 255 characters.');
        }

        return new self($namespace, $group, $item);
    }

    /**
     * @throws InvalidKey
     */
    public static function json(string $key): self
    {
        if (trim($key) === '') {
            throw new InvalidKey($key, 'it is empty.');
        }
        if (mb_strlen($key) > 2000) {
            throw new InvalidKey(mb_substr($key, 0, 40).'…', 'keys are limited to 2,000 characters.');
        }

        return new self(self::APP, self::JSON, $key);
    }

    /**
     * Rebuild a key from the three parts stored in the database or sent by a
     * form.
     *
     * @throws InvalidKey
     */
    public static function fromParts(?string $namespace, ?string $group, string $item): self
    {
        return ($group === null || $group === '' || $group === self::JSON)
            ? self::json($item)
            : self::group($group, $item, $namespace);
    }

    /**
     * Split a key the way Laravel does: "ns::group.item" or "group.item".
     * Whether a dotted string is a JSON key or a file key is the catalogue's
     * call (see Catalogue::resolve()); this one assumes a file key.
     *
     * @throws InvalidKey
     */
    public static function parse(string $key): self
    {
        $namespace = null;
        if (str_contains($key, '::')) {
            [$namespace, $key] = explode('::', $key, 2);
        }

        if (! str_contains($key, '.')) {
            throw new InvalidKey($key, 'a key in a PHP file looks like file.key.');
        }

        [$group, $item] = explode('.', $key, 2);

        return self::group($group, $item, $namespace);
    }

    public function isJson(): bool
    {
        return $this->group === self::JSON;
    }

    /**
     * What you would pass to __().
     */
    public function full(): string
    {
        if ($this->isJson()) {
            return $this->item;
        }

        return ($this->namespace === self::APP ? '' : $this->namespace.'::').$this->group.'.'.$this->item;
    }

    /**
     * "billing", "courier::messages" or "*" for the JSON file.
     */
    public function file(): string
    {
        return self::fileId($this->namespace, $this->group);
    }

    public static function fileId(string $namespace, string $group): string
    {
        if ($group === self::JSON) {
            return self::JSON;
        }

        return ($namespace === self::APP ? '' : $namespace.'::').$group;
    }

    public function hash(): string
    {
        return sha1($this->namespace."\0".$this->group."\0".$this->item);
    }

    public function equals(self $other): bool
    {
        return $this->hash() === $other->hash();
    }

    /**
     * @return array{namespace: string, group: string, key: string}
     */
    public function toArray(): array
    {
        return ['namespace' => $this->namespace, 'group' => $this->group, 'key' => $this->item];
    }

    public function __toString(): string
    {
        return $this->full();
    }
}
