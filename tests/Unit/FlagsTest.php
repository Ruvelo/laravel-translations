<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Unit;

use Ruvelo\Translations\Support\Flags;
use Ruvelo\Translations\Tests\TestCase;

class FlagsTest extends TestCase
{
    protected function tearDown(): void
    {
        Flags::flush();

        parent::tearDown();
    }

    private function decoded(?string $uri): string
    {
        $this->assertNotNull($uri);

        return (string) base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')));
    }

    private function file(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/flags/'.$name.'.svg');
    }

    public function test_a_region_wins_then_the_language(): void
    {
        $this->assertSame($this->file('pt-br'), $this->decoded(Flags::for('pt_BR')));
        $this->assertSame($this->file('fr'), $this->decoded(Flags::for('fr_CA')));
        $this->assertSame($this->file('zh'), $this->decoded(Flags::for('zh-Hant')));
    }

    public function test_unknown_locales_have_no_flag(): void
    {
        $this->assertNull(Flags::for('qq'));
        $this->assertNull(Flags::for('../../etc/passwd'));
    }

    public function test_config_can_map_a_locale_or_turn_flags_off(): void
    {
        config(['translations.flags' => ['en' => 'en-us']]);
        $this->assertSame($this->file('en-us'), $this->decoded(Flags::for('en')));

        Flags::flush();
        config(['translations.flags' => false]);
        $this->assertNull(Flags::for('fr'));
    }
}
