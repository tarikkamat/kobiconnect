<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IdempotencyKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->uuid(),
            'user_id' => null,
            'endpoint' => 'POST /api/v1/products',
            'request_hash' => hash('sha256', fake()->uuid()),
            'response_status' => null,
            'response_body' => null,
            'locked_at' => now(),
            'expires_at' => now()->addDay(),
        ];
    }
}
