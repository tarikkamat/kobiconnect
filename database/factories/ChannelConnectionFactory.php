<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\ChannelConnection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChannelConnection>
 */
class ChannelConnectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sellerId = (string) fake()->unique()->numberBetween(100000, 999999);

        return [
            'marketplace' => 'trendyol',
            'name' => fake()->company().' Trendyol',
            // Uretim sekliyle ayni: ConnectionRequest seller_id'yi credentials
            // ICINE yazar ve external_seller_id'ye AYNALAR. Factory bunu
            // yansitmazsa TrendyolCredentials::fromArray() bos sellerId alir ve
            // dogrulamada patlar — testler gercegi temsil etmez.
            'credentials' => [
                'seller_id' => $sellerId,
                'api_key' => fake()->uuid(),
                'api_secret' => fake()->uuid(),
                'integrator' => 'SelfIntegration',
                'stage' => false,
                'listing_tier' => '50k',
            ],
            'external_seller_id' => $sellerId,
            'status' => ConnectionStatus::Active,
            'settings' => [],
            'field_overrides' => [],
            'webhook_token' => Str::random(40),
            'last_health_check_at' => null,
            'capabilities' => [],
        ];
    }
}
