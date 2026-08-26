<?php

declare(strict_types=1);

namespace App\Queries\Dashboard;

use App\Models\OrderLine;

/**
 * Barkodu katalogda bulunamayan siparis satirlari. Operatorun ilk bakacagi
 * yer: siparis kaydedildi ama hangi urun oldugu bilinmiyor, hazirlanamaz.
 */
final class UnmatchedLines
{
    /**
     * @return array{lines: int, orders: int}
     */
    public function get(): array
    {
        return [
            'lines' => OrderLine::query()->whereNull('variant_id')->count(),
            'orders' => OrderLine::query()->whereNull('variant_id')->distinct()->count('order_id'),
        ];
    }
}
