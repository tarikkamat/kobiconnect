<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PriceListType;
use App\Enums\RoundingMethod;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property PriceListType $type
 * @property string $source_currency
 * @property string $target_currency
 * @property string|null $exchange_rate
 * @property RoundingMethod $rounding_method
 * @property bool $is_active
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'type',
    'source_currency',
    'target_currency',
    'exchange_rate',
    'rounding_method',
    'is_active',
    'description',
])]
class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;

    /** @return HasMany<PriceListRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(PriceListRule::class)->orderBy('position');
    }

    /** @return HasMany<PriceListItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PriceListItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PriceListType::class,
            'rounding_method' => RoundingMethod::class,
            'exchange_rate' => 'decimal:6',
            'is_active' => 'boolean',
        ];
    }
}
