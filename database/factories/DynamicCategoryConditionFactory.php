<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DynamicCategoryField;
use App\Enums\DynamicCategoryOperator;
use App\Models\DynamicCategory;
use App\Models\DynamicCategoryCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DynamicCategoryCondition>
 */
class DynamicCategoryConditionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dynamic_category_id' => DynamicCategory::factory(),
            'field' => DynamicCategoryField::Name,
            'operator' => DynamicCategoryOperator::Contains,
            'value' => fake()->word(),
        ];
    }
}
