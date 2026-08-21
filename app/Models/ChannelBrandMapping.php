<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChannelBrandMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $connection_id
 * @property int $brand_id
 * @property string $remote_brand_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['connection_id', 'brand_id', 'remote_brand_id'])]
class ChannelBrandMapping extends Model
{
    /** @use HasFactory<ChannelBrandMappingFactory> */
    use HasFactory;

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
