<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SyncCursorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $connection_id
 * @property string $resource
 * @property Carbon|null $watermark
 * @property string|null $cursor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['connection_id', 'resource', 'watermark', 'cursor'])]
class SyncCursor extends Model
{
    /** @use HasFactory<SyncCursorFactory> */
    use HasFactory;

    /** @return BelongsTo<ChannelConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'watermark' => 'datetime',
        ];
    }
}
