<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ConnectionStatus;
use App\Jobs\Sync\PullOrders;
use App\Models\ChannelConnection;
use App\Models\License;
use App\Models\SyncCursor;
use App\Models\Tenant;
use Carbon\CarbonInterface;

/**
 * The heartbeat of the pull side: every minute, ask which connections are due
 * and queue one job each. Run it every minute from the scheduler - the per
 * tenant cadence comes from the licence, not from the schedule, so a cheap plan
 * syncs less often without a second schedule entry (BACKEND-PLAN 3.2).
 *
 * Nothing is polled inline. `orders/stream` asks for five seconds between
 * requests and a cold connection walks a three month window, so the command
 * dispatches and returns; `WithoutOverlapping` on the job keeps a slow
 * connection from being started twice.
 */
final class SyncPull extends SyncCommand
{
    /**
     * @var string
     */
    protected $signature = 'sync:pull
        {--tenant=* : Yalnizca bu tenant id leri}
        {--force : Lisansin senkron araligini yoksay}';

    /**
     * @var string
     */
    protected $description = 'Vadesi gelen kanal baglantilari icin siparis cekme islerini kuyruga alir';

    public function handle(): int
    {
        $queued = 0;

        $status = $this->forLicensedTenants(function (Tenant $tenant, License $license) use (&$queued): void {
            $due = now()->subMinutes($this->intervalMinutes($license));

            $connections = ChannelConnection::query()
                ->where('status', ConnectionStatus::Active)
                ->get();

            foreach ($connections as $connection) {
                if (! $this->option('force') && ! $this->isDue($connection, $due)) {
                    continue;
                }

                PullOrders::dispatch((int) $connection->getKey());
                $queued++;
            }
        });

        $this->info("{$queued} baglanti icin siparis cekme isi kuyruga alindi.");

        return $status;
    }

    /**
     * `sync_cursors` is stamped at the end of every run, drained or not, so its
     * `updated_at` is the cheapest honest answer to "when did we last look" -
     * no extra bookkeeping table and no reliance on a run having succeeded.
     */
    private function isDue(ChannelConnection $connection, CarbonInterface $due): bool
    {
        $cursor = SyncCursor::query()
            ->where('connection_id', $connection->getKey())
            ->where('resource', 'orders')
            ->first();

        return $cursor?->updated_at === null || $cursor->updated_at->lessThanOrEqualTo($due);
    }
}
