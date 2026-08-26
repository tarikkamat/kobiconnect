<?php

declare(strict_types=1);

namespace App\Models;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $remote_package_id
 * @property string|null $cargo_provider
 * @property string|null $tracking_number int64'u asar, string
 * @property string|null $tracking_link
 * @property CanonicalOrderStatus $status
 * @property string $external_status
 * @property string|null $deci
 * @property CarbonImmutable|null $shipped_at
 * @property CarbonImmutable|null $delivered_at
 */
class ShipmentPackage extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => CanonicalOrderStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
