<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcessingStatus;
use App\Marketplaces\Data\Enums\SyncDirection;
use Database\Factories\SyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $connection_id
 * @property string $resource
 * @property SyncDirection $direction
 * @property string|null $cursor_from
 * @property string|null $cursor_to
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property array<string, mixed> $stats
 * @property ProcessingStatus $status
 * @property array<string, mixed>|null $error
 */
#[Fillable([
    'connection_id', 'resource', 'direction', 'cursor_from', 'cursor_to',
    'started_at', 'finished_at', 'stats', 'status', 'error',
])]
#[WithoutTimestamps]
class SyncRun extends Model
{
    /** @use HasFactory<SyncRunFactory> */
    use HasFactory, MassPrunable;

    /**
     * Days of sync history kept before pruning.
     *
     * ponytail: a constant, not config. Make it configurable when a tenant
     * actually asks for a different retention window.
     */
    private const RETENTION_DAYS = 30;

    /** @return BelongsTo<ChannelConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }

    /**
     * Get the prunable model query.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('started_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => SyncDirection::class,
            'status' => ProcessingStatus::class,
            'stats' => 'array',
            'error' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
