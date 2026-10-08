<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Tests\Feature;

use Ruvelo\Translations\Tests\TestCase;

class JsonApiOffTest extends TestCase
{
    public function test_the_api_is_off_by_default(): void
    {
        $this->actingAs($this->editor())->getJson('/api/translations/locales')->assertNotFound();
    }
}
