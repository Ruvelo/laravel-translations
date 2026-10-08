<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ruvelo\Translations\Entry;
use Ruvelo\Translations\Translations;

/**
 * Pending changes, for CI or a script that writes them into a checkout.
 */
class ChangeController
{
    public function index(Request $request): JsonResponse
    {
        $locale = $request->query('locale');

        return response()->json([
            'data' => array_map(fn (Entry $entry) => $entry->toArray(), Translations::pending(is_string($locale) && $locale !== '' ? $locale : null)),
        ]);
    }
}
