<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Scanner;

use Ruvelo\Translations\Key;

/**
 * What translations:scan found.
 */
final class ScanResult
{
    /**
     * @param  array<string, list<string>>  $used  Key as written => where ("app/Http/Foo.php:12")
     * @param  list<Key>  $missing  Used in code, not in the source locale
     * @param  list<Key>  $unused  In the source locale, not found in code
     * @param  list<string>  $prefixes  Partial keys built at runtime, e.g. 'status.' in __('status.'.$name)
     */
    public function __construct(
        public readonly array $used,
        public readonly array $missing,
        public readonly array $unused,
        public readonly array $prefixes,
        public readonly int $files,
    ) {}

    /**
     * @return list<string>
     */
    public function locations(Key|string $key): array
    {
        return $this->used[$key instanceof Key ? $key->full() : $key] ?? [];
    }
}
