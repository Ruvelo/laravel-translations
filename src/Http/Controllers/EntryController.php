<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Ruvelo\Translations\Http\Concerns\ResolvesKeys;
use Ruvelo\Translations\Translations;

/**
 * Saves and reverts single translations, for the editor table and the
 * in-context panel. Answers JSON to fetch(), redirects plain forms back.
 */
class EntryController
{
    use ResolvesKeys;

    public function update(Request $request, string $locale): JsonResponse|RedirectResponse
    {
        $this->assertLocale($locale);
        $key = $this->keyFrom($request, $locale);
        $request->validate(['value' => ['present', 'nullable', 'string', 'max:20000']]);

        $entry = Translations::set($locale, $key, (string) $request->input('value', ''), $request->user());

        if ($request->expectsJson()) {
            return response()->json(['entry' => $entry->toArray(), 'message' => 'Saved.']);
        }

        $message = 'Saved “'.Str::limit($key->full(), 60).'”.';
        if ($entry->warnings() !== []) {
            $message .= ' Check it: '.implode(' ', $entry->warnings());
        }

        return $this->back($request, $entry->id())->with('translations.status', $message);
    }

    public function destroy(Request $request, string $locale): JsonResponse|RedirectResponse
    {
        $this->assertLocale($locale);
        $key = $this->keyFrom($request, $locale);

        Translations::forget($locale, $key, $request->user());
        $entry = Translations::entry($locale, $key);

        if ($request->expectsJson()) {
            return response()->json(['entry' => $entry->toArray(), 'message' => 'Reverted to the lang file.']);
        }

        return $this->back($request, $entry->id())->with('translations.status', 'Reverted “'.Str::limit($key->full(), 60).'” to the lang file.');
    }

    private function back(Request $request, string $anchor): RedirectResponse
    {
        $previous = url()->previous();

        // Back to the row that was saved, on the editor page.
        return redirect()->to(str_contains($previous, '#') || ! str_contains($previous, '/'.trim((string) config('translations.path', 'translations'), '/').'/')
            ? $previous
            : $previous.'#'.$anchor);
    }
}
