<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sync;

use App\Enums\ProcessingStatus;
use App\Http\Controllers\Controller;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelOperation;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use Carbon\CarbonInterface;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The channel x resource matrix of `sync_runs`, plus where each cursor stands.
 *
 * This is the operator's view of an asynchronous system: Nightwatch answers
 * "what did the code do", this answers "is my Trendyol stock current".
 */
class SyncMonitorController extends Controller
{
    /**
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     *
     * @var array<string, string>
     */
    private const array STATUS_LABELS = [
        'pending' => 'Beklemede',
        'running' => 'Çalışıyor',
        'completed' => 'Tamamlandı',
        'failed' => 'Başarısız',
    ];

    /**
     * @var array<string, string>
     */
    private const array RESOURCE_LABELS = [
        'orders' => 'Siparişler',
        'products' => 'Ürünler',
        'claims' => 'İadeler',
        'questions' => 'Sorular',
        'stock' => 'Stok',
        'prices' => 'Fiyatlar',
    ];

    public function index(): Response
    {
        // ponytail: the matrix is built from the last 200 runs instead of a
        // DISTINCT ON query. One tenant has a handful of connections times a
        // handful of resources; swap in DISTINCT ON when that stops being true.
        $runs = SyncRun::query()
            ->with('connection:id,name,marketplace')
            ->orderByDesc('started_at')
            ->limit(200)
            ->get();

        $cursors = SyncCursor::query()
            ->get()
            ->keyBy(fn (SyncCursor $cursor): string => $cursor->connection_id.':'.$cursor->resource);

        $matrix = $runs
            ->unique(fn (SyncRun $run): string => $run->connection_id.':'.$run->resource)
            ->values()
            ->map(function (SyncRun $run) use ($cursors): array {
                $cursor = $cursors->get($run->connection_id.':'.$run->resource);

                return [
                    'id' => $run->getKey(),
                    'connectionId' => $run->connection_id,
                    'connection' => $run->connection->name,
                    'marketplace' => $run->connection->marketplace,
                    'resource' => $run->resource,
                    'resourceLabel' => self::RESOURCE_LABELS[$run->resource] ?? $run->resource,
                    'direction' => $run->direction->value,
                    'status' => $run->status->value,
                    'statusLabel' => self::STATUS_LABELS[$run->status->value],
                    'startedAt' => $this->moment($run->started_at),
                    'durationSeconds' => $run->finished_at === null
                        ? null
                        : (int) $run->finished_at->diffInSeconds($run->started_at, absolute: true),
                    'items' => (int) ($run->stats['items'] ?? 0),
                    'pages' => (int) ($run->stats['pages'] ?? 0),
                    'error' => is_string($run->error['message'] ?? null) ? $run->error['message'] : null,
                    'watermark' => $this->moment($cursor?->watermark),
                ];
            })
            ->all();

        return Inertia::render('sync/monitor', [
            'runs' => $matrix,
            'ledger' => $this->ledger(),
            'failedRuns' => $runs
                ->where('status', ProcessingStatus::Failed)
                ->take(10)
                ->values()
                ->map(fn (SyncRun $run): array => [
                    'id' => $run->getKey(),
                    'connection' => $run->connection->name,
                    'resource' => self::RESOURCE_LABELS[$run->resource] ?? $run->resource,
                    'startedAt' => $this->moment($run->started_at),
                    'message' => is_string($run->error['message'] ?? null) ? $run->error['message'] : null,
                ])
                ->all(),
        ]);
    }

    /**
     * The outbox at a glance: how much is owed, how much is in the air.
     *
     * @return array<string, int>
     */
    private function ledger(): array
    {
        $counts = ChannelOperation::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $ledger = [];

        foreach (SyncState::cases() as $state) {
            $ledger[$state->value] = (int) $counts->get($state->value, 0);
        }

        return $ledger;
    }

    /**
     * Tarihler sunucuda bicimlenir, Europe/Istanbul — FRONTEND-PLAN §7.
     */
    private function moment(?CarbonInterface $moment): ?string
    {
        return $moment?->timezone('Europe/Istanbul')->format('d.m.Y H:i');
    }
}
