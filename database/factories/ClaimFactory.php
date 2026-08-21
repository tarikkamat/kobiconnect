<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Marketplaces\Data\Enums\CanonicalClaimStatus;
use App\Models\ChannelConnection;
use App\Models\Claim;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Claim>
 */
class ClaimFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => fn (): int => self::seedOrder(),
            'remote_claim_id' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'status' => CanonicalClaimStatus::WaitingAction,
            'external_status' => 'WaitingInAction',
            'reason' => 'Ürün beklediğim gibi değil',
            'opened_at' => now()->subDay(),
            'raw' => [],
        ];
    }

    public function accepted(): static
    {
        return $this->state([
            'status' => CanonicalClaimStatus::Accepted,
            'external_status' => 'Accepted',
        ]);
    }

    /**
     * ponytail: Order'in factory'si yok — ImportOrders siparisleri DB::table
     * ile yaziyor. Talep testleri icin gereken minimum siparis satirini burada
     * uretiyoruz; OrderFactory eklenirse burasi `Order::factory()` olur.
     */
    private static function seedOrder(): int
    {
        return (int) DB::table('orders')->insertGetId([
            'connection_id' => ChannelConnection::factory()->create()->getKey(),
            'remote_id' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'remote_order_number' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
            'status' => 'delivered',
            'external_status' => 'Delivered',
            'currency' => 'TRY',
            'placed_at' => now()->subWeek(),
            'remote_last_modified_at' => now()->subDay(),
            'totals' => json_encode(['net' => '199.9000']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
