<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The `attributes` column is intentionally undocumented here: an `@property`
 * tag would collide with Eloquent's own protected `$attributes` array.
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $barcode
 * @property string|null $weight
 * @property array<string, mixed>|null $dimensions
 * @property string $vat_rate
 * @property string|null $hs_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['product_id', 'sku', 'barcode', 'attributes', 'weight', 'dimensions', 'vat_rate', 'hs_code'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<ProductImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }

    /** @return HasMany<InventoryItem, $this> */
    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'variant_id');
    }

    /** @return HasMany<Price, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'variant_id');
    }

    /** @return HasMany<ChannelListing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(ChannelListing::class, 'variant_id');
    }

    /**
     * `attributes` kolonunun tipli okumasi. Kolon `@property` ile
     * belgelenemiyor (Eloquent'in korumali `$attributes` dizisiyle cakisir),
     * bu yuzden statik analizin gorebildigi tek dogru yol burasi.
     *
     * @return array<string, mixed>|null
     */
    public function attributeValues(): ?array
    {
        $values = $this->getAttribute('attributes');

        return is_array($values) ? $values : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'dimensions' => 'array',
            'weight' => 'decimal:3',
            'vat_rate' => 'decimal:2',
        ];
    }
}
