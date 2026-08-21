<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChannelAttributeMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $connection_id
 * @property string $remote_category_id
 * @property int $attribute_id
 * @property string $remote_attribute_id
 * @property bool $is_required
 * @property bool $allow_custom
 * @property bool $allow_multiple
 * @property bool $is_varianter
 * @property bool $is_slicer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'connection_id', 'remote_category_id', 'attribute_id', 'remote_attribute_id',
    'is_required', 'allow_custom', 'allow_multiple', 'is_varianter', 'is_slicer',
])]
class ChannelAttributeMapping extends Model
{
    /** @use HasFactory<ChannelAttributeMappingFactory> */
    use HasFactory;

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /** @return HasMany<ChannelAttributeValueMapping, $this> */
    public function valueMappings(): HasMany
    {
        return $this->hasMany(ChannelAttributeValueMapping::class, 'mapping_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'allow_custom' => 'boolean',
            'allow_multiple' => 'boolean',
            'is_varianter' => 'boolean',
            'is_slicer' => 'boolean',
        ];
    }
}
