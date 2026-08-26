<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'description'])]
class ProductGroup extends Model
{
    /** @use HasFactory<ProductGroupFactory> */
    use HasFactory;

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_group_product')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
