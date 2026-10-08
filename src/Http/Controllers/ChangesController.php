<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers;

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
            'editors' => Translations::editorNames($pending),
        ]);
    }

    /**
     * Editor names in one query.
     *
     * @param  list<Entry>  $entries
     * @return array<string, string>
     */
}
