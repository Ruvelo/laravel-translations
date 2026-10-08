<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Ruvelo\Translations\Translations;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every route: only users who pass the `translations-edit` gate.
 *
 * Guests are sent to the app's login page when it has one. A fresh app has
 * no `login` route until a starter kit is installed, and Laravel's own
 * `auth` middleware fails with a 500 there; this answers 401/403 instead.
 */
class AuthorizeEditing
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Translations::canEdit($request->user())) {
            return $next($request);
        }

        if ($request->user() === null) {
            abort_if($request->expectsJson(), 401);

            if (Route::has('login')) {
                return redirect()->guest(route('login'));
            }
        }

        abort(403);
    }
}
