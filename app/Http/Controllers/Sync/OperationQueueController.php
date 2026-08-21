<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Jobs\Sync\DrainChannelOperations;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Support\Sync\IdempotencyGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The outbox ledger as a screen: every row's life from pending to a marketplace
 * item result, and the button that puts a failed one back.
 *
 * A retry re-opens the *desired state*, never a stored request body. The drain
 * rebuilds the payload from it, which is the only retry Trendyol's fifteen
 * minute suppression window tolerates (TRENDYOL.md K3).
 */
class OperationQueueController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const array STATUS_LABELS = [
        'pending' => 'Beklemede',
        'in_flight' => 'Gönderildi',
        'completed' => 'Tamamlandı',
        'failed' => 'Başarısız',
    ];

    /**
     * @var array<string, string>
     */
    private const array OPERATION_LABELS = [
        'product_create' => 'Ürün oluştur',
        'product_update' => 'Ürün güncelle',
        'price_update' => 'Fiyat gönder',
        'stock_update' => 'Stok gönder',
        'shipment_status' => 'Kargo durumu',
        'tracking_number' => 'Takip numarası',
        'claim_approve' => 'İade onayla',
        'claim_reject' => 'İade reddet',
        'question_answer' => 'Soru yanıtla',
    ];

    /**
     * Rows re-opened by a single bulk retry.
     */
    private const RETRY_LIMIT = 500;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(SyncState::class)],
            'connection' => ['nullable', 'integer'],
        ]);

        // Validation hands back the raw string, not the enum instance.
        $status = $filters['status'] ?? null;
        $connection = isset($filters['connection']) ? (int) $filters['connection'] : null;

        $operations = ChannelOperation::query()
            ->with('connection:id,name')
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($connection !== null, fn (Builder $query) => $query->where('connection_id', $connection))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (ChannelOperation $operation): array => [
                'id' => $operation->getKey(),
                'connection' => $operation->connection->name,
                'entity' => class_basename($operation->entity_type).' #'.$operation->entity_id,
                'operation' => $operation->operation,
                'operationLabel' => self::OPERATION_LABELS[$operation->operation] ?? $operation->operation,
                'status' => $operation->status->value,
                'statusLabel' => self::STATUS_LABELS[$operation->status->value],
                'attempts' => $operation->attempts,
                'remoteBatchId' => $operation->remote_batch_id,
                'message' => $this->message($operation),
                'scheduledAt' => $operation->scheduled_at->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                'sentAt' => $operation->sent_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
                'completedAt' => $operation->completed_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
            ]);

        return Inertia::render('sync/operations', [
            'operations' => $operations,
            'filters' => ['status' => $status, 'connection' => $connection],
            'statuses' => array_map(
                fn (SyncState $state): array => [
                    'value' => $state->value,
                    'label' => self::STATUS_LABELS[$state->value],
                ],
                SyncState::cases(),
            ),
            'connections' => ChannelConnection::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Re-open failed rows: by id, or every failure of the current filter.
     */
    public function retry(Request $request, IdempotencyGuard $guard): HttpResponse
    {
        Gate::authorize('channels.manage');

        $replay = $guard->claim($request);

        if ($replay !== null) {
            return $replay;
        }

        $input = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'connection' => ['nullable', 'integer'],
        ]);

        /** @var list<int> $ids */
        $ids = $input['ids'] ?? [];

        $operations = ChannelOperation::query()
            ->where('status', SyncState::Failed)
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('id', $ids))
            ->when(
                isset($input['connection']),
                fn (Builder $query) => $query->where('connection_id', (int) $input['connection']),
            )
            ->orderBy('id')
            ->limit(self::RETRY_LIMIT)
            ->get();

        $reopened = 0;
        $drains = [];

        foreach ($operations as $operation) {
            $type = OperationType::tryFrom($operation->operation);

            if ($type === null) {
                continue;
            }

            try {
                $operation->update([
                    'status' => SyncState::Pending,
                    'scheduled_at' => now(),
                    'sent_at' => null,
                    'completed_at' => null,
                    'remote_batch_id' => null,
                    'remote_result' => null,
                    'error' => null,
                ]);
            } catch (UniqueConstraintViolationException) {
                // An open row already carries this exact desired state; this one
                // owes nothing and stays failed as the historical record.
                continue;
            }

            $reopened++;
            $drains[$operation->connection_id.':'.$type->value] = [$operation->connection_id, $type];
        }

        foreach ($drains as [$connectionId, $type]) {
            DrainChannelOperations::dispatch($connectionId, $type);
        }

        Inertia::flash('toast', [
            'type' => $reopened > 0 ? 'success' : 'error',
            'message' => $reopened > 0
                ? "{$reopened} işlem yeniden kuyruğa alındı."
                : 'Yeniden denenecek işlem bulunamadı.',
        ]);

        return $guard->complete($request, back());
    }

    /**
     * The one line an operator needs: why it failed, or what it is waiting for.
     */
    private function message(ChannelOperation $operation): ?string
    {
        foreach ([$operation->error, $operation->remote_result] as $source) {
            $message = $source['message'] ?? null;

            if (is_string($message) && $message !== '') {
                return $message;
            }
        }

        $skipped = $operation->remote_result['skipped'] ?? null;

        return $skipped === 'marketplace_dedup_window'
            ? 'Aynı değerler pazaryeri penceresi içinde gönderilmişti, tekrar gönderilmedi.'
            : null;
    }
}
