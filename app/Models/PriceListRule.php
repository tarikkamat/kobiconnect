<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdjustmentType;
use App\Enums\PriceRuleField;
use Database\Factories\PriceListRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $price_list_id
 * @property PriceRuleField $field
 * @property mixed $condition_value
 * @property AdjustmentType $adjustment_type
 * @property string $adjustment_value
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'price_list_id',
    'field',
    'condition_value',
    'adjustment_type',
    'adjustment_value',
    'position',
])]
class PriceListRule extends Model
{
    /** @use HasFactory<PriceListRuleFactory> */
    use HasFactory;

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => PriceRuleField::class,
            'condition_value' => 'json',
            'adjustment_type' => AdjustmentType::class,
            'adjustment_value' => 'decimal:2',
            'position' => 'integer',
        ];
    }
}
