<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProcessingStatus;
use App\Marketplaces\Data\Enums\SyncDirection;
use App\Models\ChannelConnection;
use App\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncRun>
 */
class SyncRunFactory extends Factory
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
            'resource' => 'orders',
            'direction' => SyncDirection::Pull,
            'cursor_from' => null,
            'cursor_to' => null,
            'started_at' => now(),
            'finished_at' => null,
            'stats' => [],
            'status' => ProcessingStatus::Running,
            'error' => null,
        ];
    }
}
