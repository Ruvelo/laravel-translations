<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Ruvelo\Translations\Exceptions\InvalidKey;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Translations;

trait ResolvesKeys
{
    /**
     * The key a form or API call names: `key` as written in __(), or
     * `group` (and `namespace`) plus `key` to be explicit.
     *
     * @throws ValidationException
     */
    protected function keyFrom(Request $request, string $locale): Key
    {
        $request->validate([
            'key' => ['required', 'string', 'max:2000'],
            'group' => ['nullable', 'string', 'max:150'],
            'namespace' => ['nullable', 'string', 'max:100'],
        ]);

        $item = (string) $request->input('key');
        $group = $request->input('group');
        $namespace = $request->input('namespace');

        try {
            return is_string($group) && $group !== ''
                ? Key::fromParts(is_string($namespace) ? $namespace : null, $group, $item)
                : Translations::key($item, $locale);
        } catch (InvalidKey $e) {
            throw ValidationException::withMessages(['key' => $e->getMessage()]);
        }
    }

    protected function assertLocale(string $locale): void
    {
        abort_unless(Translations::catalogue()->hasLocale($locale), 404);
    }
}
