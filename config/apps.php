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

    ],

];
