<?php

declare(strict_types=1);

namespace Ruvelo\Translations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Ruvelo\Translations\Key;
use Ruvelo\Translations\Models\Override;

/**
 * For your own tests:
 *
 *     Override::factory()->locale('fr')->key('billing.title')->value('Facturation')->create();
 *     Override::factory()->json('Pay now')->create();
 *
 * @extends Factory<Override>
 */
final class OverrideFactory extends Factory
{
    protected $model = Override::class;

    public function definition(): array
    {
        $key = Key::json(rtrim($this->faker->unique()->sentence(3), '.'));

        return [
            'locale' => 'fr',
            ...$key->toArray(),
            'hash' => $key->hash(),
            'value' => $this->faker->sentence(4),
            'file_value' => null,
        ];
    }

    public function locale(string $locale): self
    {
        return $this->state(['locale' => $locale]);
    }

    /**
     * A key in a PHP file: 'billing.invoice.title' or 'courier::messages.hello'.
     */
    public function key(string $key): self
    {
        $key = Key::parse($key);

        return $this->state([...$key->toArray(), 'hash' => $key->hash()]);
    }

    public function json(string $key): self
    {
        $key = Key::json($key);

        return $this->state([...$key->toArray(), 'hash' => $key->hash()]);
    }

    public function value(string $value): self
    {
        return $this->state(['value' => $value]);
    }
}
