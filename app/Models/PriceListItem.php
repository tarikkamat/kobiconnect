<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PriceListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $price_list_id
 * @property int $variant_id
 * @property string $list_price
 * @property string|null $sale_price
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'price_list_id',
    'variant_id',
    'list_price',
    'sale_price',
    'currency',
])]
class PriceListItem extends Model
{
    /** @use HasFactory<PriceListItemFactory> */
    use HasFactory;

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

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
        ];
    }
}
