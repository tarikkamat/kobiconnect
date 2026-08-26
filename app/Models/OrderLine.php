<?php

declare(strict_types=1);

namespace App\Models;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $variant_id
 * @property string $remote_line_id
 * @property string $sku
 * @property string|null $barcode
 * @property int $quantity
 * @property string $unit_price
 * @property array<string, mixed> $discounts
 * @property string|null $commission oran, tutar degil
 * @property string|null $vat_rate
 * @property CanonicalOrderStatus $status
 * @property string $external_status
 * @property ProductVariant|null $variant
 */
class OrderLine extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => CanonicalOrderStatus::class,
            'discounts' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Bilerek nullable: pazaryerinden katalogda karsiligi olmayan bir barkod
     * gelebilir. Siparis reddedilmez, satir "eslesmemis" kuyruguna duser.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
