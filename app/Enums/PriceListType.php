<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\HasLabels;

enum PriceListType: string
{
    use HasLabels;

    case Manual = 'manual';
    case Currency = 'currency';
    case Dynamic = 'dynamic';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manuel Fiyat Listesi',
            self::Currency => 'Kura Göre Fiyat Listesi',
            self::Dynamic => 'Dinamik Fiyat Listesi',
        };
    }
}
