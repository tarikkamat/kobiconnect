<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.fake()->unique()->numerify('########'),
            'barcode' => fake()->unique()->ean13(),
            'attributes' => [],
            'weight' => fake()->randomFloat(3, 0.1, 20),
            'dimensions' => ['width' => 10, 'height' => 20, 'depth' => 5],
            'vat_rate' => 20,
            'hs_code' => null,
        ];
    }
}
