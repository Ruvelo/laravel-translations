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

        // Overall progress counts every string in every language but the
        // source, so a big, nearly empty language weighs what it should.
        $targets = $locales->reject(fn (array $locale) => $locale['progress']->isSource);
        $total = $targets->sum(fn (array $locale) => $locale['progress']->total);
        $translated = $targets->sum(fn (array $locale) => $locale['progress']->translated);

        $pending = Translations::pending();
        $recent = array_slice($pending, 0, 6);

        return response()->view('translations::index', [
            'locales' => $locales,
            'pending' => count($pending),
            'source' => Translations::sourceLocale(),
            'overall' => [
                'percent' => $total > 0 ? (int) floor($translated / $total * 100) : 100,
                'missing' => $total - $translated,
                'languages' => $targets->count(),
            ],
            'recent' => $recent,
            'editors' => Translations::editorNames($recent),
        ]);
    }
}
