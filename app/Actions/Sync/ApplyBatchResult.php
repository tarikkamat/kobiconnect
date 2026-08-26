<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Events\NotificationEventOccurred;
use App\Marketplaces\Data\Enums\SyncState;
use App\Marketplaces\Data\PushResult;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Notifications\NotificationEvent;
use App\Support\Sync\MarketplaceWindow;
use Illuminate\Support\Collection;

/**
 * Closes ledger rows against the item level results of a push.
 *
 * The rule this enforces (BACKEND-PLAN 7.2, TRENDYOL.md K1): an accepted batch
 * is not a success. `status: COMPLETED` with `failedItemCount > 0` is a normal
 * partial success, so every row is judged by its own item result and never by
 * the envelope. A row with no item result yet stays open.
 */
final class ApplyBatchResult
{
    public function __construct(private readonly MarketplaceWindow $window) {}

    /**
     * @param  Collection<int, ChannelOperation>  $operations
     * @param  bool  $final  the batch will report nothing further, so a row
     *                       without an item result has lost its result for good
     */
    public function __invoke(Collection $operations, PushResult $result, bool $final = false): void
    {
        foreach ($operations as $operation) {
            $item = $result->itemResults[$this->reference($operation)] ?? null;

            if ($item === null) {
                if ($final) {
                    $this->fail($operation, [
                        'code' => 'missing_item_result',
                        'message' => 'Pazaryeri bu kalem için sonuç döndürmedi.',
                    ]);
                }

                continue;
            }

            if ($item['accepted']) {
                $operation->update([
                    'status' => SyncState::Completed,
                    'completed_at' => now(),
                    'remote_result' => $item,
                    'error' => null,
                ]);

                if ($operation->entity_type === 'product') {
                    NotificationEventOccurred::dispatch(NotificationEvent::ProductApproved, [
                        'product_id' => (string) $operation->entity_id,
                        'sku' => $operation->desired_state['reference'] ?? '',
                        'connection_id' => (string) $operation->connection_id,
                        'connection' => ChannelConnection::find($operation->connection_id)->name ?? '',
                    ]);
                }

                continue;
            }

            $this->fail($operation, $item, $item);
        }
    }

    /**
     * @param  array<string, mixed>  $error
     * @param  array<string, mixed>|null  $remoteResult
     */
    private function fail(ChannelOperation $operation, array $error, ?array $remoteResult = null): void
    {
        $this->window->forget($operation);

        $operation->update([
            'status' => SyncState::Failed,
            'completed_at' => now(),
            'remote_result' => $remoteResult,
            'error' => $error,
        ]);

        if ($operation->entity_type === 'product') {
            NotificationEventOccurred::dispatch(NotificationEvent::ProductRejected, [
                'product_id' => (string) $operation->entity_id,
                'connection_id' => (string) $operation->connection_id,
                'connection' => ChannelConnection::find($operation->connection_id)->name ?? '',
                'reason' => $error['message'] ?? ($error['code'] ?? 'Bilinmeyen hata'),
            ]);
        }
    }

    /**
     * Item results are keyed by the reference the canonical DTO carried, which
     * is exactly what the ledger stored as desired state.
     */
    private function reference(ChannelOperation $operation): string
    {
        $reference = $operation->desired_state['reference'] ?? null;

        return is_string($reference) && $reference !== ''
            ? $reference
            : $operation->entity_type.'#'.$operation->entity_id;
    }
}
