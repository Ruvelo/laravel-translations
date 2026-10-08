<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Laravel\Ai\AiServiceProvider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Prompts\AgentPrompt;
use Ruvelo\Translations\Suggestions\Suggester;
use Ruvelo\Translations\Suggestions\TranslationAgent;
use Ruvelo\Translations\Tests\TestCase;
use Ruvelo\Translations\Translations;

class SuggestionTest extends TestCase
{
    protected function setUp(): void
    {
        if (! interface_exists(Agent::class)) {
            $this->markTestSkipped('laravel/ai is not installed.');
        }

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), AiServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('ai.default', 'openai');
        $app['config']->set('ai.providers.openai', ['driver' => 'openai', 'key' => 'test']);
    }

    public function test_the_ai_sdk_drafts_a_translation(): void
    {
        TranslationAgent::fake(['Facture :number']);

        $this->assertTrue(Translations::canSuggest());
        $this->assertSame('Facture :number', Translations::suggest('fr', 'billing.invoice.title'));

        TranslationAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, 'Invoice :number')
            && str_contains($prompt->prompt, 'billing.invoice.title'));
    }

    public function test_the_agent_is_told_to_keep_placeholders(): void
    {
        $instructions = (new TranslationAgent('en', 'de'))->instructions();

        $this->assertStringContainsString('from en to de', $instructions);
        $this->assertStringContainsString(':name', $instructions);
        $this->assertStringContainsString('|', $instructions);
    }

    public function test_the_suggest_endpoint_returns_a_draft_and_saves_nothing(): void
    {
        TranslationAgent::fake(['Facture']);

        $this->actingAs($this->editor())
            ->postJson('/translations/fr/suggest', ['group' => 'billing', 'key' => 'invoice.title'])
            ->assertOk()
            ->assertExactJson(['suggestion' => 'Facture', 'warnings' => ['Missing :number, which the source text uses.']]);

        $this->assertNull(Translations::get('fr', 'billing.invoice.title'));
    }

    public function test_the_button_shows_only_when_suggestions_work(): void
    {
        $editor = $this->editor();

        $this->actingAs($editor)->get('/translations/fr')->assertSee('data-trans-suggest', false);
        // Never on the source locale: there's nothing to translate from.
        $this->actingAs($editor)->get('/translations/en')->assertDontSee('data-trans-suggest hidden', false);

        config()->set('translations.suggestions.enabled', false);
        $this->actingAs($editor)->get('/translations/fr')->assertDontSee('data-trans-suggest hidden', false);
    }

    public function test_failures_are_reported(): void
    {
        TranslationAgent::fake(fn () => throw new \RuntimeException('quota exceeded'));

        $this->actingAs($this->editor())
            ->postJson('/translations/fr/suggest', ['group' => 'billing', 'key' => 'invoice.title'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'The suggestion service failed: quota exceeded');
    }

    public function test_a_key_with_no_source_text_cannot_be_suggested(): void
    {
        $this->actingAs($this->editor())
            ->postJson('/translations/fr/suggest', ['group' => 'billing', 'key' => 'nothing.here'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'There is no source text to translate.');
    }

    public function test_suggestions_are_rate_limited(): void
    {
        TranslationAgent::fake(['Facture :number']);
        config()->set('translations.suggestions.per_minute', 2);
        $editor = $this->editor();

        foreach ([200, 200, 429] as $status) {
            $this->actingAs($editor)->postJson('/translations/fr/suggest', ['key' => 'billing.invoice.title'])->assertStatus($status);
        }
    }

    public function test_your_own_suggester(): void
    {
        config()->set('ai.default', null);
        $this->app->bind(Suggester::class, fn () => new class implements Suggester
        {
            public function suggest(string $text, string $from, string $to, string $key = ''): string
            {
                return strtoupper("{$to}: {$text}");
            }
        });

        $this->assertTrue(Translations::canSuggest());
        $this->assertSame('FR: CANCEL SUBSCRIPTION', Translations::suggest('fr', 'billing.cancel'));
    }
}
