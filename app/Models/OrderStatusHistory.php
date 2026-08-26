<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $package_id
 * @property string|null $from_status
 * @property string $to_status
 * @property CarbonImmutable $occurred_at
 * @property string $source
 */
class OrderStatusHistory extends Model
{
    protected $table = 'order_status_history';

    public const UPDATED_AT = null;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<ShipmentPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ShipmentPackage::class, 'package_id');
    }
}
