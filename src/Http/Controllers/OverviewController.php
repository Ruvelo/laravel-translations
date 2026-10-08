<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Ruvelo\Translations\Translations;

class OverviewController
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        // The locale switcher in the editor, without JavaScript.
        $switch = $request->query('locale');
        if (is_string($switch) && Translations::catalogue()->hasLocale($switch)) {
            return redirect()->route('translations.editor', $switch);
        }

        $locales = collect(Translations::locales())->map(fn (string $locale) => [
            'code' => $locale,
            'name' => Translations::localeName($locale),
            'progress' => Translations::progress($locale),
        ]);

        return response()->view('translations::index', [
            'locales' => $locales,
            'pending' => count(Translations::pending()),
            'source' => Translations::sourceLocale(),
        ]);
    }
}
