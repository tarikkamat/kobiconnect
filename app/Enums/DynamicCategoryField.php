<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\HasLabels;

enum DynamicCategoryField: string
{
    use HasLabels;

    case Brand = 'brand';
    case Price = 'price';
    case Tag = 'tag';
    case VariantValue = 'variant_value';
    case OnSale = 'on_sale';
    case CreatedAt = 'created_at';
    case Campaign = 'campaign';
    case Category = 'category';
    case Name = 'name';

    public function label(): string
    {
        return match ($this) {
            self::Brand => 'Ürün Markası',
            self::Price => 'Ürün Fiyatı',
            self::Tag => 'Ürün Etiketi',
            self::VariantValue => 'Varyant Değeri',
            self::OnSale => 'İndirimli Ürünler',
            self::CreatedAt => 'Oluşturulma Tarihi',
            self::Campaign => 'Kampanya',
            self::Category => 'Kategori',
            self::Name => 'Ürün Adı',
        };
    }
}
