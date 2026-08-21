<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $variant_id
 * @property string $currency
 * @property string $list_price
 * @property string|null $sale_price
 * @property string|null $cost
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['variant_id', 'currency', 'list_price', 'sale_price', 'cost', 'valid_from', 'valid_to'])]
class Price extends Model
{
    /** @use HasFactory<PriceFactory> */
    use HasFactory;

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
        ];
    }
}
