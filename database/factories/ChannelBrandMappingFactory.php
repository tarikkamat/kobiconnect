<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use App\Models\ChannelBrandMapping;
use App\Models\ChannelConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelBrandMapping>
 */
class ChannelBrandMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_id' => ChannelConnection::factory(),
            'brand_id' => Brand::factory(),
            'remote_brand_id' => (string) fake()->numberBetween(100, 99999),
        ];
    }
}
