<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'type' => AttributeType::Select,
            'is_variant_defining' => false,
        ];
    }

    /**
     * Indicate that the attribute distinguishes variants of a product.
     */
    public function variantDefining(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_variant_defining' => true,
        ]);
    }
}
