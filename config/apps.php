<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Uygulama Magazasi
    |--------------------------------------------------------------------------
    |
    | Magazadaki her kart burada tanimlidir. Bir uygulamanin KURULABILIR olup
    | olmadigi buradan degil, `config/marketplaces.php` icindeki surucu
    | kaydindan gelir: surucusu olmayan uygulama vitrinde "Yakinda" durur.
    | Boylece tek bir kod iki yerde "var" diyemez.
    |
    | `price`:
    |   null                                  -> plana dahil, ayrica ucretlendirilmez
    |   ['monthly' => 149, 'yearly' => 1490]  -> app basina abonelik (TL)
    |
    | Bugun tum uygulamalar plana dahildir. App basina ucretlendirmeye
    | gecildiginde degisecek olan sey bu dosyadaki degerler ve
    | App\Support\AppCatalog::entitled() icindeki TEK kosuldur — vitrin, kart
    | rozetleri ve fiyat etiketi zaten bu iki kaynagi okur.
    |
    */

    'categories' => [
        'marketplace' => 'Pazaryerleri',
        'ecommerce' => 'E-ticaret altyapıları',
    ],

    'apps' => [

        'trendyol' => [
            'name' => 'Trendyol',
            'category' => 'marketplace',
            'summary' => 'Ürün, stok, fiyat ve sipariş akışının tamamı. Toplu işlem sonuçları takip edilir, başarısız satır ürününüzde işaretlenir.',
            'price' => null,
        ],

        'hepsiburada' => [
            'name' => 'Hepsiburada',
            'category' => 'marketplace',
            'summary' => 'Katalog eşleştirme, listeleme, stok-fiyat ve sipariş. Eşleşme önerileri onayınıza düşer; onaylanmayan ürün satışa çıkmaz.',
            'price' => null,
        ],

        'n11' => [
            'name' => 'n11',
            'category' => 'marketplace',
            'summary' => 'n11 mağazanız için ürün, stok, fiyat ve sipariş aktarımı.',
            'price' => null,
        ],

        'amazon' => [
            'name' => 'Amazon',
            'category' => 'marketplace',
            'summary' => 'Amazon Türkiye satıcı hesabınız için SP-API üzerinden ürün ve sipariş aktarımı.',
            'price' => null,
        ],

        'ciceksepeti' => [
            'name' => 'Çiçeksepeti',
            'category' => 'marketplace',
            'summary' => 'Çiçeksepeti mağazanız için ürün, stok, fiyat ve sipariş aktarımı.',
            'price' => null,
        ],

        'pazarama' => [
            'name' => 'Pazarama',
            'category' => 'marketplace',
            'summary' => 'Pazarama mağazanız için ürün, stok, fiyat ve sipariş aktarımı.',
            'price' => null,
        ],

        'pttavm' => [
            'name' => 'PttAVM',
            'category' => 'marketplace',
            'summary' => 'PttAVM mağazanız için ürün, stok, fiyat ve sipariş aktarımı.',
            'price' => null,
        ],

        'shopify' => [
            'name' => 'Shopify',
            'category' => 'ecommerce',
            'summary' => 'Kendi Shopify mağazanızı da bir kanal gibi yönetin: ürünler tek katalogdan beslenir, siparişler aynı ekrana düşer.',
            'price' => null,
        ],

        'ikas' => [
            'name' => 'ikas',
            'category' => 'ecommerce',
            'summary' => 'ikas mağazanızdaki ürün ve siparişleri KobiConnect kataloğuyla senkronize edin.',
            'price' => null,
        ],

        'ideasoft' => [
            'name' => 'IdeaSoft',
            'category' => 'ecommerce',
            'summary' => 'IdeaSoft altyapılı mağazanız için ürün, stok ve sipariş aktarımı.',
            'price' => null,
        ],

        'ticimax' => [
            'name' => 'Ticimax',
            'category' => 'ecommerce',
            'summary' => 'Ticimax altyapılı mağazanız için ürün, stok ve sipariş aktarımı.',
            'price' => null,
        ],

        'woocommerce' => [
            'name' => 'WooCommerce',
            'category' => 'ecommerce',
            'summary' => 'WooCommerce mağazanız için REST API üzerinden ürün, stok ve sipariş aktarımı.',
            'price' => null,
        ],

    ],

];
