<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\HasLabels;

enum PriceRuleField: string
{
    use HasLabels;

    case All = 'all';
    case Category = 'category';
    case Brand = 'brand';
    case Tag = 'tag';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tüm Ürünler',
            self::Category => 'Kategoriye Göre',
            self::Brand => 'Markaya Göre',
            self::Tag => 'Etikete Göre',
            self::Product => 'Belirli Ürüne Göre',
        };
    }
}
