<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\HasLabels;

enum ProductStatus: string
{
    use HasLabels;

    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Active => 'Aktif',
            self::Archived => 'Arşivlendi',
        };
    }
}
