<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Ruvelo\Translations\Exceptions\SuggestionsUnavailable;
use Ruvelo\Translations\Http\Concerns\ResolvesKeys;
use Ruvelo\Translations\Translations;

/**
 * Drafts a translation for the editor to review. Nothing is saved.
 */
class SuggestionController
{
    use ResolvesKeys;

    public function __invoke(Request $request, string $locale): JsonResponse
    {
        $this->assertLocale($locale);
        $key = $this->keyFrom($request, $locale);

        try {
            $suggestion = Translations::suggest($locale, $key);
        } catch (SuggestionsUnavailable $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $source = (string) Translations::value(Translations::sourceLocale(), $key);

        return response()->json([
            'suggestion' => $suggestion,
            'warnings' => Translations::check($source, $suggestion),
        ]);
    }
}
