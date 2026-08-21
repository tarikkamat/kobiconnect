<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ListingSyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelListing>
 */
class ChannelListingFactory extends Factory
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
            'variant_id' => ProductVariant::factory(),
            'remote_id' => null,
            'remote_status' => null,
            'remote_payload_hash' => null,
            'sync_state' => ListingSyncState::Pending,
            'last_pushed_at' => null,
            'last_pulled_at' => null,
            'error' => null,
        ];
    }

    /**
     * Indicate that the listing exists on the marketplace.
     */
    public function synced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'remote_id' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'sync_state' => ListingSyncState::Synced,
            'last_pushed_at' => now(),
        ]);
    }
}
