<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Paused => 'Duraklatıldı',
            self::Error => 'Hata',
        };
    }
}
