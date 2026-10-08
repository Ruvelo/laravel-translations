<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ruvelo\Translations\Exceptions\TranslationsException;
use Ruvelo\Translations\Translations;

class LocaleController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => array_map(fn (string $locale) => [
            'code' => $locale,
            'name' => Translations::localeName($locale),
            ...Translations::progress($locale)->toArray(),
        ], Translations::locales())]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        try {
            $locale = Translations::addLocale((string) $request->input('code'), $request->user());
        } catch (TranslationsException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['code' => [$e->getMessage()]]], 422);
        }

        return response()->json(['data' => [
            'code' => $locale,
            'name' => Translations::localeName($locale),
            ...Translations::progress($locale)->toArray(),
        ]], 201);
    }
}
