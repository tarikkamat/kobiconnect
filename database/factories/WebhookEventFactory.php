<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Models\ChannelConnection;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = ['status' => 'Created', 'id' => fake()->uuid()];

        return [
            'connection_id' => ChannelConnection::factory(),
            'marketplace' => 'trendyol',
            'external_ref' => fake()->uuid(),
            'headers' => ['content-type' => 'application/json'],
            'payload' => $payload,
            'payload_hash' => hash('sha256', (string) json_encode($payload)),
            'received_at' => now(),
            'processed_at' => null,
            'status' => ProcessingStatus::Pending,
            'error' => null,
        ];
    }
}
