<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Suggestions;

/**
 * Drafts a translation. Bind your own implementation to use another
 * service: $this->app->bind(Suggester::class, DeepLSuggester::class).
 */
interface Suggester
{
    /**
     * @param  string  $key  The translation key, as a hint about where the text is used
     */
    public function suggest(string $text, string $from, string $to, string $key = ''): string;
}
