<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Ruvelo\Translations\Translations;
use Ruvelo\Translations\View\Components\Toolbar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the "Edit translations" button to your app's pages, for editors
 * only, just before </body>. Pages that already render
 * <x-translations::toolbar /> are left alone.
 */
class InjectToolbar
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $content = (string) $response->getContent();
        $position = strripos($content, '</body>');

        if ($position === false || str_contains($content, Toolbar::MARKER)) {
            return $response;
        }

        $toolbar = (new Toolbar)->render()->render();
        $response->setContent(substr($content, 0, $position).$toolbar.substr($content, $position));

        return $response;
    }

    private function shouldInject(Request $request, Response $response): bool
    {
        return config('translations.in_context.enabled', true)
            && $response instanceof IlluminateResponse
            && $response->getStatusCode() === 200
            && $request->isMethod('GET')
            && ! $request->expectsJson()
            && str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html')
            && ! $request->routeIs('translations.*')
            && Translations::canEdit($request->user());
    }
}
