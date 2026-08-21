<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChannelCategoryMappingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $connection_id
 * @property int $category_id
 * @property string $remote_category_id
 * @property string|null $remote_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['connection_id', 'category_id', 'remote_category_id', 'remote_path'])]
class ChannelCategoryMapping extends Model
{
    /** @use HasFactory<ChannelCategoryMappingFactory> */
    use HasFactory;

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
