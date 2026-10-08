<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Ruvelo\Translations\Exceptions\TranslationsException;
use Ruvelo\Translations\Translations;

class LocaleController
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        try {
            $locale = Translations::addLocale((string) $request->input('code'), $request->user());
        } catch (TranslationsException $e) {
            return back()->withInput()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()->route('translations.editor', $locale)
            ->with('translations.status', 'Added '.Translations::localeName($locale).'. Everything starts as missing: translate away.');
    }
}
