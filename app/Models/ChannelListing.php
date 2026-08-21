<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ListingSyncState;
use Database\Factories\ChannelListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $connection_id
 * @property int $variant_id
 * @property string|null $remote_id
 * @property string|null $remote_status
 * @property string|null $remote_payload_hash
 * @property ListingSyncState $sync_state
 * @property Carbon|null $last_pushed_at
 * @property Carbon|null $last_pulled_at
 * @property array<string, mixed>|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'connection_id', 'variant_id', 'remote_id', 'remote_status', 'remote_payload_hash',
    'sync_state', 'last_pushed_at', 'last_pulled_at', 'error',
])]
class ChannelListing extends Model
{
    /** @use HasFactory<ChannelListingFactory> */
    use HasFactory;

    /** @return BelongsTo<ChannelConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sync_state' => ListingSyncState::class,
            'error' => 'array',
            'last_pushed_at' => 'datetime',
            'last_pulled_at' => 'datetime',
        ];
    }
}
