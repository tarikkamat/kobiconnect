<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ChannelConnection;
use App\Models\SyncCursor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncCursor>
 */
class SyncCursorFactory extends Factory
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
            'watermark' => now()->subHour(),
            'cursor' => null,
        ];
    }
}
