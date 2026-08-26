<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DynamicCategoryMatchType;
use Database\Factories\DynamicCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property DynamicCategoryMatchType $match_type
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'match_type', 'description'])]
class DynamicCategory extends Model
{
    /** @use HasFactory<DynamicCategoryFactory> */
    use HasFactory;

    /** @return HasMany<DynamicCategoryCondition, $this> */
    public function conditions(): HasMany
    {
        return $this->hasMany(DynamicCategoryCondition::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'dynamic_category_products')
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_type' => DynamicCategoryMatchType::class,
        ];
    }
}
