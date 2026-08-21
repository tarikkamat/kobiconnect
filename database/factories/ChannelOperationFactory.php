<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelOperation>
 */
class ChannelOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $desiredState = ['quantity' => fake()->numberBetween(0, 100)];

        return [
            'connection_id' => ChannelConnection::factory(),
            'entity_type' => ProductVariant::class,
            'entity_id' => fake()->numberBetween(1, 100000),
            'operation' => 'update_price_and_stock',
            'desired_state' => $desiredState,
            'payload' => null,
            'payload_hash' => hash('sha256', (string) json_encode($desiredState)),
            'idempotency_key' => fake()->uuid(),
            'status' => SyncState::Pending,
            'attempts' => 0,
            'remote_batch_id' => null,
            'remote_result' => null,
            'scheduled_at' => now(),
            'sent_at' => null,
            'completed_at' => null,
            'error' => null,
        ];
    }

    /**
     * Indicate that the operation has been handed to the marketplace.
     */
    public function inFlight(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SyncState::InFlight,
            'remote_batch_id' => fake()->uuid(),
            'sent_at' => now(),
            'attempts' => 1,
        ]);
    }
}
