<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Actions\Orders\ImportOrders;
use App\Enums\ConnectionStatus;
use App\Enums\ProcessingStatus;
use App\Marketplaces\Data\Enums\SyncDirection;
use App\Models\ChannelConnection;
use App\Models\SyncRun;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Pulls one connection's orders - BACKEND-PLAN 7.1.
 *
 * `ImportOrders` owns the stream loop, the cursor and the upserts; this job
 * owns the two things that only make sense around a scheduled run: the overlap
 * lock, so a slow connection is never walked twice at once, and the `sync_runs`
 * row, which is the operator facing record of what happened (Nightwatch is for
 * developers, `sync_runs` is for the person asking why an order is missing).
 *
 * ponytail: the run bookkeeping duplicates what `RunPull` does, because
 * ImportOrders keeps its own cursor rather than going through it. Fold the two
 * together when someone owns both files - the seam is the `fetch`/`sink` pair.
 */
final class PullOrders implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $connectionId)
    {
        $this->onQueue('sync-orders');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("connection:{$this->connectionId}:orders"))
                ->releaseAfter(60)
                ->expireAfter(900),
        ];
    }

    public function handle(ImportOrders $import): void
    {
        $connection = ChannelConnection::query()->find($this->connectionId);

        if ($connection === null || $connection->status !== ConnectionStatus::Active) {
            return;
        }

        $run = SyncRun::create([
            'connection_id' => $connection->getKey(),
            'resource' => 'orders',
            'direction' => SyncDirection::Pull,
            'started_at' => now(),
            'stats' => [],
            'status' => ProcessingStatus::Running,
        ]);

        try {
            $stats = $import->handle($connection);
        } catch (Throwable $exception) {
            $run->update([
                'status' => ProcessingStatus::Failed,
                'finished_at' => now(),
                'error' => ['class' => $exception::class, 'message' => $exception->getMessage()],
            ]);

            throw $exception;
        }

        $run->update([
            'status' => ProcessingStatus::Completed,
            'finished_at' => now(),
            'stats' => $stats,
        ]);
    }
}
