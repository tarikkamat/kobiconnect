<?php

declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Enums\ConnectionStatus;
use App\Models\ChannelConnection;
use App\Support\AppTime;

/**
 * Kanal baglanti durumu. Hata veren bir baglanti sessizce durur; panelin bunu
 * soylemesi gerekir.
 */
final class ConnectionHealth
{
    /**
     * @return array{items: list<array<string, mixed>>, errored: int}
     */
    public function get(): array
    {
        $connections = ChannelConnection::query()
            ->orderBy('name')
            ->get(['id', 'name', 'marketplace', 'status', 'last_health_check_at']);

        return [
            'errored' => $connections->where('status', ConnectionStatus::Error)->count(),
            'items' => array_values($connections
                ->map(fn (ChannelConnection $connection): array => [
                    'id' => $connection->getKey(),
                    'name' => $connection->name,
                    // Logo yolu koddan turetilir (/apps/{kod}.svg) — AppCatalog
                    // ile ayni kaynak, bkz. AppCatalog::present().
                    'marketplace' => $connection->marketplace,
                    'status' => $connection->status->value,
                    'statusLabel' => $connection->status->label(),
                    'checkedAt' => AppTime::dateTime($connection->last_health_check_at),
                ])
                ->all()),
        ];
    }
}
