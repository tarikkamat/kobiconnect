<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attribute;
use App\Models\ChannelAttributeMapping;
use App\Models\ChannelConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelAttributeMapping>
 */
class ChannelAttributeMappingFactory extends Factory
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
            'remote_category_id' => (string) fake()->numberBetween(100, 9999),
            'attribute_id' => Attribute::factory(),
            'remote_attribute_id' => (string) fake()->numberBetween(100, 9999),
            'is_required' => false,
            'allow_custom' => false,
            'allow_multiple' => false,
            'is_varianter' => false,
            'is_slicer' => false,
        ];
    }
}
