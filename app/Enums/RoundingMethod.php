<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\HasLabels;

enum RoundingMethod: string
{
    use HasLabels;

    case None = 'none';
    case Round = 'round';
    case Ceil = 'ceil';
    case Floor = 'floor';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Yuvarlama Yok',
            self::Round => 'En Yakına Yuvarla (Standart)',
            self::Ceil => 'Yukarı Yuvarla',
            self::Floor => 'Aşağı Yuvarla',
        };
    }
}
