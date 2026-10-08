<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ruvelo\Translations\Entry;
use Ruvelo\Translations\Http\Concerns\ResolvesKeys;
use Ruvelo\Translations\Http\Controllers\EditorController;
use Ruvelo\Translations\Translations;

class EntryController
{
    use ResolvesKeys;

    public function index(Request $request, string $locale): JsonResponse
    {
        $this->assertLocale($locale);

        $request->validate([
            'filter' => ['nullable', 'in:'.implode(',', EditorController::FILTERS)],
            'q' => ['nullable', 'string', 'max:200'],
            'file' => ['nullable', 'string', 'max:300'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $entries = Translations::entries(
            $locale,
            (string) ($request->input('filter') ?? 'all'),
            (string) ($request->input('q') ?? ''),
            is_string($request->input('file')) ? $request->input('file') : null,
        );

        $perPage = (int) ($request->input('per_page') ?? 100);
        $page = (int) ($request->input('page') ?? 1);

        return response()->json([
            'data' => $entries->forPage($page, $perPage)->map(fn (Entry $entry) => $entry->toArray())->values(),
            'meta' => [
                'locale' => $locale,
                'source_locale' => Translations::sourceLocale(),
                'total' => $entries->count(),
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($entries->count() / $perPage)),
            ],
        ]);
    }

    public function update(Request $request, string $locale): JsonResponse
    {
        $this->assertLocale($locale);
        $key = $this->keyFrom($request, $locale);
        $request->validate(['value' => ['present', 'nullable', 'string', 'max:20000']]);

        $entry = Translations::set($locale, $key, (string) $request->input('value', ''), $request->user());

        return response()->json(['data' => $entry->toArray()]);
    }

    public function destroy(Request $request, string $locale): Response
    {
        $this->assertLocale($locale);
        Translations::forget($locale, $this->keyFrom($request, $locale), $request->user());

        return response()->noContent();
    }
}
