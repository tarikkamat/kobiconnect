<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdjustmentType;
use App\Enums\PriceRuleField;
use App\Models\PriceList;
use App\Models\PriceListRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceListRule>
 */
class PriceListRuleFactory extends Factory
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
            'field' => PriceRuleField::All,
            'condition_value' => null,
            'adjustment_type' => AdjustmentType::Percentage,
            'adjustment_value' => 10.00,
            'position' => 0,
        ];
    }
}
