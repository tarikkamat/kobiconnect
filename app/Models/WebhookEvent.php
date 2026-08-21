<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProcessingStatus;
use Database\Factories\WebhookEventFactory;
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
 * @property string $marketplace
 * @property string|null $external_ref
 * @property array<string, mixed> $headers
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 * @property ProcessingStatus $status
 * @property array<string, mixed>|null $error
 */
#[Fillable([
    'connection_id', 'marketplace', 'external_ref', 'headers', 'payload',
    'payload_hash', 'received_at', 'processed_at', 'status', 'error',
])]
#[WithoutTimestamps]
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory, MassPrunable;

    /**
     * Days of raw webhook payloads kept before pruning.
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
        return static::query()->where('received_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProcessingStatus::class,
            'headers' => 'array',
            'payload' => 'array',
            'error' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
