<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceListItem>
 */
class PriceListItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_list_id' => PriceList::factory(),
            'variant_id' => ProductVariant::factory(),
            'list_price' => fake()->randomFloat(2, 50, 1000),
            'sale_price' => null,
            'currency' => 'TRY',
        ];
    }
}
