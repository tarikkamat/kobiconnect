<?php

declare(strict_types=1);

namespace App\Models;

use App\Marketplaces\Data\Enums\CanonicalOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
