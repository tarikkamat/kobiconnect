<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AllocationType;
use App\Enums\RuleScope;
use App\Models\ChannelConnection;
use App\Models\ChannelStockRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelStockRule>
 */
class ChannelStockRuleFactory extends Factory
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
            'scope_type' => RuleScope::Connection,
            'scope_id' => null,
            'allocation_type' => AllocationType::Percent,
            'allocation_value' => 100,
            'buffer' => 0,
        ];
    }
}
