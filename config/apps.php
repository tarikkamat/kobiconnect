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
    | `logo_scale` (istege bagli): logonun kendi tuvalindeki bosluk yuzunden
    | optik olarak kucuk kalan markalar icin carpan.
    |
    | `logo_dark_invert` (istege bagli): koyu wordmark'lar karanlik temada
    | gorunmez; bu bayrak logoyu karanlik temada tamamen beyaza boyar. Renkli
    | logolar (n11, WooCommerce...) bayraksiz kalir, oldugu gibi gorunur.
    |
    | Fiyatlandirma ve lisanslama BILEREK yok: kurgusu urun bittiginde
    | yapilacak. Girdiginde bu dosyaya bir `price` anahtari ve vitrine bir
    | rozet duser; kart yapisi degismez.
    |
    */

    'categories' => [
        'marketplace' => 'Pazaryerleri',
        'ecommerce' => 'E-ticaret altyapıları',
    ],

    'apps' => [

        'trendyol' => [
            'logo_dark_invert' => true,
            'name' => 'Trendyol',
            'category' => 'marketplace',
        ],

        'hepsiburada' => [
            'name' => 'Hepsiburada',
            'category' => 'marketplace',
        ],

        'n11' => [
            'name' => 'n11',
            'category' => 'marketplace',
        ],

        'amazon' => [
            'logo_dark_invert' => true,
            'name' => 'Amazon',
            'category' => 'marketplace',
        ],

        'ciceksepeti' => [
            'name' => 'Çiçeksepeti',
            'category' => 'marketplace',
            // Logo kendi tuvalinde kucuk cizilmis; vitrinde digerleriyle ayni
            // optik boyuta gelmesi icin buyutuluyor.
            'logo_scale' => 1.5,
        ],

        'pazarama' => [
            'name' => 'Pazarama',
            'category' => 'marketplace',
        ],

        'pttavm' => [
            'name' => 'PttAVM',
            'category' => 'marketplace',
        ],

        'shopify' => [
            'logo_dark_invert' => true,
            'name' => 'Shopify',
            'category' => 'ecommerce',
        ],

        'ikas' => [
            'logo_dark_invert' => true,
            'name' => 'ikas',
            'category' => 'ecommerce',
        ],

        'ideasoft' => [
            'name' => 'IdeaSoft',
            'category' => 'ecommerce',
            'logo_scale' => 1.5,
        ],

        'ticimax' => [
            'logo_dark_invert' => true,
            'name' => 'Ticimax',
            'category' => 'ecommerce',
        ],

        'woocommerce' => [
            'name' => 'WooCommerce',
            'category' => 'ecommerce',
        ],

    ],

];
