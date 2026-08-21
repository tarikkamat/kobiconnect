<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChannelAttributeValueMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $mapping_id
 * @property int $attribute_value_id
 * @property string $remote_value_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['mapping_id', 'attribute_value_id', 'remote_value_id'])]
class ChannelAttributeValueMapping extends Model
{
    /** @use HasFactory<ChannelAttributeValueMappingFactory> */
    use HasFactory;

    /** @return BelongsTo<AttributeValue, $this> */
    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class);
    }
}
