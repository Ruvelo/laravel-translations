<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Turns in-context editing on or off for this session.
 */
class EditModeController
{
    public const SESSION_KEY = 'translations.edit_mode';

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        $on = $request->boolean('on');
        $request->session()->put(self::SESSION_KEY, $on);

        if ($request->expectsJson()) {
            return response()->json(['on' => $on]);
        }

        return back();
    }
}
