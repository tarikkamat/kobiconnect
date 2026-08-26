<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DynamicCategoryMatchType;
use App\Models\DynamicCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DynamicCategory>
 */
class DynamicCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = (string) fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'match_type' => DynamicCategoryMatchType::All,
            'description' => fake()->sentence(),
        ];
    }
}
