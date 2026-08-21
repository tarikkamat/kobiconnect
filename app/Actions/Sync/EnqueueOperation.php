<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Jobs\Sync\DrainChannelOperations;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Writes one intent into the outbox ledger - BACKEND-PLAN 7.2.
 *
 *   desired_state -> payload_hash -> idempotency_key -> [send] ->
 *   remote_batch_id -> item_result -> completed
 *
 * `channel_operations` is a ledger, not a queue. Redis knows *when* work runs;
 * this table knows *what* has to be true remotely and *what happened*. Domain
 * code never calls a marketplace: it records the state it wants and returns.
 *
 * Two things are deliberate here:
 *
 * - The row carries the desired *state*, not a wire payload. A retry therefore
 *   recomputes the request instead of replaying bytes, which is the only safe
 *   retry against Trendyol's 15 minute suppression window (TRENDYOL.md K3).
 * - Pending rows coalesce. "Changed forty times in ten seconds" leaves one
 *   pending row carrying the last state, so the debounced drain sends once.
 *   An in flight row is never mutated - it has already left the building.
 */
final class EnqueueOperation
{
    /**
     * @param  object  $desiredState  a canonical DTO (StockData, PriceData, ProductData...)
     */
    public function __invoke(
        ChannelConnection $connection,
        OperationType $operation,
        string $entityType,
        int $entityId,
        object $desiredState,
        ?CarbonInterface $scheduledAt = null,
    ): ChannelOperation {
        $state = $this->toArray($desiredState);
        $hash = hash('sha256', (string) json_encode($state));
        $scheduledAt ??= now();

        // Dedup layer 3: deterministic, and backed by the partial unique index
        // on (connection_id, idempotency_key) WHERE status IN (pending, in_flight).
        $key = hash('sha256', implode('|', [
            (string) $connection->getKey(),
            $entityType.'#'.$entityId,
            $operation->value,
            $hash,
        ]));

        $record = DB::transaction(fn (): ChannelOperation => $this->write(
            $connection, $operation, $entityType, $entityId, $state, $hash, $key, $scheduledAt,
        ));

        DrainChannelOperations::dispatch($connection->getKey(), $operation)->afterCommit();

        return $record;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function write(
        ChannelConnection $connection,
        OperationType $operation,
        string $entityType,
        int $entityId,
        array $state,
        string $hash,
        string $key,
        CarbonInterface $scheduledAt,
    ): ChannelOperation {
        $pending = ChannelOperation::query()
            ->where('connection_id', $connection->getKey())
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('operation', $operation->value)
            ->where('status', SyncState::Pending)
            ->lockForUpdate()
            ->first();

        if ($pending !== null) {
            if ($pending->payload_hash === $hash) {
                return $pending;
            }

            try {
                $pending->update([
                    'desired_state' => $state,
                    'payload' => null,
                    'payload_hash' => $hash,
                    'idempotency_key' => $key,
                    'scheduled_at' => $scheduledAt,
                    'error' => null,
                ]);

                return $pending;
            } catch (UniqueConstraintViolationException) {
                // An in flight row already carries exactly this state, so the
                // pending one owes nothing: drop it and let that row report.
                $pending->delete();

                return $this->open($connection, $key);
            }
        }

        $inserted = ChannelOperation::query()->toBase()->insertOrIgnoreReturning([
            'connection_id' => $connection->getKey(),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'operation' => $operation->value,
            'desired_state' => (string) json_encode($state),
            'payload' => null,
            'payload_hash' => $hash,
            'idempotency_key' => $key,
            'status' => SyncState::Pending->value,
            'attempts' => 0,
            'scheduled_at' => $scheduledAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = $inserted->first();

        return $row === null
            ? $this->open($connection, $key)
            : (new ChannelOperation)->newFromBuilder((array) $row);
    }

    /**
     * The open row that won the uniqueness race.
     */
    private function open(ChannelConnection $connection, string $key): ChannelOperation
    {
        return ChannelOperation::query()
            ->where('connection_id', $connection->getKey())
            ->where('idempotency_key', $key)
            ->whereIn('status', [SyncState::Pending, SyncState::InFlight])
            ->firstOrFail();
    }

    /**
     * Canonical DTOs are plain readonly objects, so their own shape is the
     * ledger's shape - no serializer to keep in step with them.
     *
     * @return array<string, mixed>
     */
    private function toArray(object $desiredState): array
    {
        /** @var array<string, mixed> $state */
        $state = json_decode((string) json_encode($desiredState), true);

        return $state;
    }
}
