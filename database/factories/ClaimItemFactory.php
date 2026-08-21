<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Marketplaces\Data\Enums\CanonicalClaimStatus;
use App\Models\Claim;
use App\Models\ClaimItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClaimItem>
 */
class ClaimItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'claim_id' => Claim::factory(),
            'order_line_id' => null,
            'remote_item_id' => (string) fake()->unique()->numberBetween(1000000, 9999999),
            'quantity' => 1,
            'status' => CanonicalClaimStatus::WaitingAction,
            'external_status' => 'WaitingInAction',
            'reason' => 'Beden uymadı',
        ];
    }
}
