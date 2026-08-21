<?php

declare(strict_types=1);

namespace App\Models;

use App\Marketplaces\Data\Enums\SyncState;
use Database\Factories\ChannelOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The outbox ledger: one row per intended remote mutation, kept until the
 * marketplace's (delayed, item-level) result has been read back.
 *
 * @property int $id
 * @property int $connection_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $operation
 * @property array<string, mixed> $desired_state
 * @property array<string, mixed>|null $payload
 * @property string $payload_hash
 * @property string $idempotency_key
 * @property SyncState $status
 * @property int $attempts
 * @property string|null $remote_batch_id
 * @property array<string, mixed>|null $remote_result
 * @property Carbon $scheduled_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $completed_at
 * @property array<string, mixed>|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'connection_id', 'entity_type', 'entity_id', 'operation', 'desired_state', 'payload',
    'payload_hash', 'idempotency_key', 'status', 'attempts', 'remote_batch_id',
    'remote_result', 'scheduled_at', 'sent_at', 'completed_at', 'error',
])]
class ChannelOperation extends Model
{
    /** @use HasFactory<ChannelOperationFactory> */
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
            'status' => SyncState::class,
            'desired_state' => 'array',
            'payload' => 'array',
            'remote_result' => 'array',
            'error' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
