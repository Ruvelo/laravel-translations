<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Suggestions;

use Laravel\Ai\Contracts\Agent;
use Ruvelo\Translations\Exceptions\SuggestionsUnavailable;
use Throwable;

/**
 * Suggestions from the Laravel AI SDK, using your default provider unless
 * translations.suggestions.provider / model say otherwise.
 */
class LaravelAiSuggester implements Suggester
{
    public static function installed(): bool
    {
        return interface_exists(Agent::class);
    }

    /**
     * Installed, and the AI SDK has a default provider set up.
     */
    public static function configured(): bool
    {
        if (! self::installed()) {
            return false;
        }

        $provider = config('translations.suggestions.provider') ?? config('ai.default');

        return is_string($provider) && $provider !== '' && is_array(config('ai.providers.'.$provider));
    }

    public function suggest(string $text, string $from, string $to, string $key = ''): string
    {
        if (! self::installed()) {
            throw new SuggestionsUnavailable;
        }

        $prompt = ($key !== '' ? "Key (for context): {$key}\n\n" : '')."Text to translate:\n{$text}";
        $provider = config('translations.suggestions.provider');
        $model = config('translations.suggestions.model');

        try {
            $response = (new TranslationAgent($from, $to))->prompt(
                $prompt,
                provider: is_string($provider) && $provider !== '' ? $provider : null,
                model: is_string($model) && $model !== '' ? $model : null,
            );
        } catch (Throwable $e) {
            throw new SuggestionsUnavailable('The suggestion service failed: '.$e->getMessage());
        }

        return trim($response->text);
    }
}
