<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Price;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Price>
 */
class PriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'currency' => 'TRY',
            'list_price' => fake()->randomFloat(2, 50, 5000),
            'sale_price' => null,
            'cost' => null,
            'valid_from' => null,
            'valid_to' => null,
        ];
    }
}
