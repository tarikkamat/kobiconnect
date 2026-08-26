<?php

declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelOperation;

/**
 * Outbox'ta bekleyen ve basarisiz kalan islem sayisi. Son kosularin listesi
 * panelden kalkti; ayrinti Senkron Monitoru'nde.
 */
final class SyncHealth
{
    /**
     * @return array{failedOperations: int, pendingOperations: int}
     */
    public function get(): array
    {
        $byStatus = ChannelOperation::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'failedOperations' => (int) $byStatus->get(SyncState::Failed->value, 0),
            'pendingOperations' => (int) $byStatus->get(SyncState::Pending->value, 0),
        ];
    }
}
