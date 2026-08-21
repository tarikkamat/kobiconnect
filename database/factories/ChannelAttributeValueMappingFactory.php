<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttributeValue;
use App\Models\ChannelAttributeMapping;
use App\Models\ChannelAttributeValueMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelAttributeValueMapping>
 */
class ChannelAttributeValueMappingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mapping_id' => ChannelAttributeMapping::factory(),
            'attribute_value_id' => AttributeValue::factory(),
            'remote_value_id' => (string) fake()->numberBetween(100, 9999),
        ];
    }
}
