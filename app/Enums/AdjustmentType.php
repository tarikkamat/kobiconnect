<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\HasLabels;

enum AdjustmentType: string
{
    use HasLabels;

    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Yüzde (%)',
            self::Fixed => 'Sabit Tutar',
        };
    }
}
