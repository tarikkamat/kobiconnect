<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DynamicCategoryField;
use App\Enums\DynamicCategoryOperator;
use Database\Factories\DynamicCategoryConditionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dynamic_category_id
 * @property DynamicCategoryField $field
 * @property DynamicCategoryOperator $operator
 * @property mixed $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['dynamic_category_id', 'field', 'operator', 'value'])]
class DynamicCategoryCondition extends Model
{
    /** @use HasFactory<DynamicCategoryConditionFactory> */
    use HasFactory;

    /** @return BelongsTo<DynamicCategory, $this> */
    public function dynamicCategory(): BelongsTo
    {
        return $this->belongsTo(DynamicCategory::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field' => DynamicCategoryField::class,
            'operator' => DynamicCategoryOperator::class,
            'value' => 'json',
        ];
    }
}
