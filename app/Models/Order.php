<?php

declare(strict_types=1);

namespace App\Models;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $connection_id
 * @property string $remote_id
 * @property string $remote_order_number
 * @property CanonicalOrderStatus $status
 * @property string $external_status
 * @property string $currency
 * @property CarbonImmutable $placed_at
 * @property CarbonImmutable|null $remote_last_modified_at
 * @property array<string, mixed> $totals
 * @property ArrayObject<string, mixed>|null $customer
 * @property ArrayObject<string, mixed>|null $raw
 * @property int $line_count withCount('lines')
 * @property int $unmatched_count withCount eslesmemis satirlar
 * @property ChannelConnection|null $connection
 */
class Order extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => CanonicalOrderStatus::class,
            'totals' => 'array',
            // KVKK — BACKEND-PLAN.md §13. ImportOrders bu formatta yaziyor.
            'customer' => AsEncryptedArrayObject::class,
            'raw' => AsEncryptedArrayObject::class,
            'placed_at' => 'datetime',
            'remote_last_modified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ChannelConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }

    /** @return HasMany<OrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /** @return HasMany<ShipmentPackage, $this> */
    public function packages(): HasMany
    {
        return $this->hasMany(ShipmentPackage::class);
    }

    /** @return HasMany<OrderStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
