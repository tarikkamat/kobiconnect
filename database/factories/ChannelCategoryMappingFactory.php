<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\ChannelCategoryMapping;
use App\Models\ChannelConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelCategoryMapping>
 */
class ChannelCategoryMappingFactory extends Factory
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
            'category_id' => Category::factory(),
            'remote_category_id' => (string) fake()->numberBetween(100, 9999),
            'remote_path' => fake()->words(3, true),
        ];
    }
}
