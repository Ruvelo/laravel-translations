<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Suggestions;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * The Laravel AI SDK agent behind the "Suggest" button. Only loaded when
 * laravel/ai is installed. In tests: TranslationAgent::fake(['Bonjour']).
 */
class TranslationAgent implements Agent
{
    use Promptable;

    public function __construct(
        public readonly string $from = 'en',
        public readonly string $to = 'fr',
    ) {}

    public function instructions(): string
    {
        return <<<TEXT
            You translate user interface text for a web application from {$this->from} to {$this->to}.

            Rules:
            - Reply with the translation only: no quotes, notes or explanations.
            - Keep every placeholder exactly as written: words starting with a colon (:name, :Name, :NAME) and words in braces ({count}).
            - Keep plural forms: if the text has parts separated by |, translate each part and keep the same number of parts, in the same order. Keep range markers such as {0}, {1} and [2,*] at the start of their part.
            - Keep HTML tags and their attributes unchanged; translate only the text between them.
            - Match the tone and length of the original. It is interface copy: short and plain.
            TEXT;
    }
}
