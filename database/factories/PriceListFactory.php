<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PriceListType;
use App\Enums\RoundingMethod;
use App\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => (string) fake()->word().' Fiyat Listesi',
            'type' => PriceListType::Manual,
            'source_currency' => 'TRY',
            'target_currency' => 'TRY',
            'exchange_rate' => 1.0,
            'rounding_method' => RoundingMethod::None,
            'is_active' => true,
            'description' => fake()->sentence(),
        ];
    }
}
