<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MarkupType;
use App\Enums\RuleScope;
use App\Models\ChannelConnection;
use App\Models\ChannelPriceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelPriceRule>
 */
class ChannelPriceRuleFactory extends Factory
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
            'markup_type' => MarkupType::Percent,
            'markup_value' => 10,
            'round_to' => null,
        ];
    }
}
