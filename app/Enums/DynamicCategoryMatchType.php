<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\HasLabels;

enum DynamicCategoryMatchType: string
{
    use HasLabels;

    case All = 'all'; // Tüm koşulları sağlamalı
    case Any = 'any'; // En az bir koşulu sağlamalı

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tüm koşulları sağlamalı (VE)',
            self::Any => 'En az bir koşulu sağlamalı (VEYA)',
        };
    }
}
