<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ConnectionStatus;
use App\Jobs\Sync\DrainChannelOperations;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelOperation;
use App\Models\Tenant;

/**
 * The safety net under the outbox.
 *
 * The happy path needs nothing from this command: `EnqueueOperation` dispatches
 * a drain the moment it writes a row. This exists for the rows that path loses
 * - a job that failed all its attempts, a row pushed back to pending with a
 * thirty second penalty, a Redis flush, a worker that was down when the row was
 * written. A ledger whose rows can only move when someone remembers to push
 * them is not a ledger.
 *
 * Dispatching every minute is safe: the drain job is debounced per
 * (connection, operation) and claims its rows with `FOR UPDATE SKIP LOCKED`.
 */
final class SyncDrain extends SyncCommand
{
    /**
     * @var string
     */
    protected $signature = 'sync:drain {--tenant=* : Yalnizca bu tenant id leri}';

    /**
     * @var string
     */
    protected $description = 'Bekleyen outbox satirlari icin drenaj islerini kuyruga alir';

    public function handle(): int
    {
        $queued = 0;

        $status = $this->forEachTenant(function (Tenant $tenant) use (&$queued): void {
            $due = ChannelOperation::query()
                ->join('channel_connections', 'channel_connections.id', '=', 'channel_operations.connection_id')
                ->where('channel_connections.status', ConnectionStatus::Active)
                ->where('channel_operations.status', SyncState::Pending)
                ->where('channel_operations.scheduled_at', '<=', now())
                ->distinct()
                ->get(['channel_operations.connection_id', 'channel_operations.operation']);

            foreach ($due as $row) {
                $operation = OperationType::tryFrom((string) $row->getAttribute('operation'));

                if ($operation === null) {
                    continue;
                }

                DrainChannelOperations::dispatch((int) $row->getAttribute('connection_id'), $operation);
                $queued++;
            }
        });

        $this->info("{$queued} baglanti/operasyon cifti icin drenaj isi kuyruga alindi.");

        return $status;
    }
}
