<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Ruvelo\Translations\Entry;
use Ruvelo\Translations\Translations;

/**
 * Pending changes: what an export would write, with a revert per change.
 */
class ChangesController
{
    public function __invoke(): Response
    {
        $pending = Translations::pending();

        return response()->view('translations::changes', [
            'groups' => collect($pending)->groupBy(fn (Entry $entry) => $entry->locale)->sortKeys(),
            'count' => count($pending),
            'editors' => $this->editors($pending),
        ]);
    }

    /**
     * Editor names in one query.
     *
     * @param  list<Entry>  $entries
     * @return array<string, string>
     */
    private function editors(array $entries): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn (Entry $entry) => $entry->override?->updated_by, $entries))));

        /** @var class-string<Model> $model */
        $model = config('translations.user_model') ?? config('auth.providers.users.model') ?? 'App\\Models\\User';

        if ($ids === [] || ! class_exists($model)) {
            return [];
        }

        $attribute = (string) config('translations.user_name_attribute', 'name');
        $names = [];

        foreach ($model::query()->whereKey($ids)->get() as $user) {
            $name = $user->getAttribute($attribute);
            if (is_string($name) && $name !== '') {
                $names[(string) $user->getKey()] = $name;
            }
        }

        return $names;
    }
}
