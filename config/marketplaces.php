<?php

use App\Marketplaces\Hepsiburada\HepsiburadaDriver;
use App\Marketplaces\Trendyol\TrendyolDriver;

return [

    /*
    |--------------------------------------------------------------------------
    | Marketplace Drivers
    |--------------------------------------------------------------------------
    |
    | Every marketplace adapter is registered here as "key => driver class".
    | The class must implement App\Marketplaces\Contracts\MarketplaceDriver.
    | Adding a marketplace is one line here plus its own folder under
    | app/Marketplaces; nothing else in the application changes.
    |
    */

    'drivers' => [
        'hepsiburada' => HepsiburadaDriver::class,
        'trendyol' => TrendyolDriver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Trendyol
    |--------------------------------------------------------------------------
    |
    | Credentials are per connection and live in channel_connections, not here.
    | What lives here is everything Trendyol changes by announcement: base urls,
    | rate limits and cache lifetimes. See TRENDYOL.md section 7 and 12.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Webhook taban adresi
    |--------------------------------------------------------------------------
    |
    | Pazaryerleri bize keyfi baslik gonderemez, bu yuzden tenant ve baglanti
    | URL'in kendisinden cozulur: {taban}/{marketplace}/{token}
    |
    | Gelistirmede uygulama host'unun altinda bir path olarak durur (ek DNS
    | gerekmez). Production'da ayri bir host'a isaret edilebilir.
    |
    */

    // `?:` bilerek: bos bir env degeri (WEBHOOK_BASE_URL=) `??` ile varsayilani
    // ezip bos string birakirdi.
    'webhook_base_url' => env('WEBHOOK_BASE_URL') ?: env('APP_URL', 'http://localhost').'/hooks',

    'hepsiburada' => [

        // Uc ayri host; istemci taban URL'i SERVISE gore secer (H1, olculdu).
        // Yapi ORTAM -> SERVIS sirasinda; istemci baseUrl() boyle cozuyor.
        // SIT host'lari olculdu: ucu de 200 dondu.
        'base_urls' => [
            'sit' => [
                'catalog' => 'https://mpop-sit.hepsiburada.com',
                'listing' => 'https://listing-external-sit.hepsiburada.com',
                'oms' => 'https://oms-external-sit.hepsiburada.com',
            ],
            'production' => [
                'catalog' => 'https://mpop.hepsiburada.com',
                'listing' => 'https://listing-external.hepsiburada.com',
                'oms' => 'https://oms-external.hepsiburada.com',
            ],
        ],

        // Katalog tarafinda size 100 ile SINIRLI.
        'page_size' => 100,

        // Sayfalama dongusunun ust siniri. `last` bayragina kosulsuz guvenmek
        // bozuk bir yanitta sonsuz dongu demektir.
        'max_pages' => 5000,

        'timeout' => 30,

        // ⚠️ Limit IP BASINADIR, satici basina degil. Tek VDS = tek cikis IP'si
        // = tum tenant'larin PAYLASTIGI butce. Bu yuzden limitleyici
        // global_cache() uzerinden calismak zorunda; tenant'a etiketlenmis bir
        // cache her tenant'a ayri 180 verir ve gercek limit katlanarak asilir.
        'rate_limits' => [
            'per_minute' => env('HEPSIBURADA_RATE_LIMIT', 180),
            'headroom' => 0.8,
            'max_waits' => 12,
        ],
    ],

    'trendyol' => [

        // The sapigw host was shut down on 26 May 2025; only these two are live.
        'base_urls' => [
            'production' => env('TRENDYOL_BASE_URL', 'https://apigw.trendyol.com/integration'),
            'stage' => env('TRENDYOL_STAGE_BASE_URL', 'https://stageapigw.trendyol.com/integration'),
        ],

        'timeout' => 30,

        // Anti-idempotency: updatePriceAndInventory ayni (barcode, degerler)
        // istegini bu pencere icinde SESSIZCE dusurur. Retry ayni govdeyi
        // replay etmez, istenen durumu yeniden hesaplar (TRENDYOL.md §3).
        'dedup_window_seconds' => env('TRENDYOL_DEDUP_WINDOW', 900),

        // getBatchRequestResult sonucu yalnizca 4 saat yasar; sonrasi
        // mutabakata duser (TRENDYOL.md §6).
        'batch_result_ttl' => 14400,

        // Full jitter exponential backoff (TRENDYOL.md 8.5). 503 is handled by
        // the client: retryable in production, a fatal IP allow list problem on
        // stage. 400/401/403/404/409 are permanent and never retried.
        'retry' => [
            'times' => 3,
            'base_delay_ms' => 1000,
            'max_delay_ms' => 60000,
            'statuses' => [429, 500, 502, 504],
        ],

        // Cache::flexible() lifetimes in seconds: [fresh, stale].
        'cache' => [
            'brands' => [86400, 172800],
            'categories' => [604800, 1209600],
            'attributes' => [604800, 1209600],
        ],

        'storefront_code' => env('TRENDYOL_STOREFRONT_CODE', 'TR'),
        'accept_language' => env('TRENDYOL_ACCEPT_LANGUAGE', 'tr'),

        // getBrands honours a minimum page size of 1000; attribute values cap
        // both page and size at 1000 (TRENDYOL.md 4.1.1, 4.1.6).
        'brand_page_size' => 1000,
        'attribute_value_page_size' => 1000,

        /*
        | Two axis rate limiting (TRENDYOL.md 7, BACKEND-PLAN.md 9.1).
        |
        | On 14 September 2026 the product limits move from per endpoint to per
        | service group, sized by the seller's product listing tier, and
        | updatePriceAndInventory goes from unlimited to 350-2000/min. That is
        | why these numbers are configuration and not constants: the switch is a
        | value change here, not a code change.
        |
        | Trendyol documents no rate limit response headers, so the client never
        | reads Retry-After or X-RateLimit-*; the budget below is authoritative.
        */
        'rate_limits' => [

            // Layer 1, above everything else: 50 requests / 10 seconds on every
            // single endpoint, i.e. an effective burst ceiling of 5 req/s.
            'endpoint' => [
                'limit' => 50,
                'seconds' => 10,
            ],

            // Aim at a share of the published limit: Trendyol narrows limits by
            // announcement and their window is not aligned with our clock.
            'headroom' => 0.7,

            // How many times acquire() may wait for a bucket before giving up
            // and letting the job be requeued.
            'max_waits' => 12,

            // The seller's product listing tier, overridable per connection
            // through TrendyolCredentials::$listingTier.
            'tier' => env('TRENDYOL_LISTING_TIER', '50k'),

            'groups' => [
                'product_read' => ['50k' => 1000, '75k' => 1250, '150k' => 1500, '500k' => 1750, 'unlimited' => 2000],
                'product_write' => ['50k' => 200, '75k' => 300, '150k' => 400, '500k' => 500, 'unlimited' => 600],
                'inventory_price_write' => ['50k' => 350, '75k' => 500, '150k' => 1000, '500k' => 1500, 'unlimited' => 2000],
                // Siparis servisleri kademeli ve urun servislerinden cok daha
                // dar — TRENDYOL.md 7.4.
                'order_read' => ['50k' => 30, '75k' => 40, '150k' => 50, '500k' => 100, 'unlimited' => 100],
            ],

            // Every endpoint the client may call has to name its group; an
            // unmapped one throws rather than borrow another group's budget.
            'endpoints' => [
                'getBrands' => 'product_read',
                'getBrandsByName' => 'product_read',
                'getCategoryTree' => 'product_read',
                'getCategoryAttributes' => 'product_read',
                'getCategoryAttributeValues' => 'product_read',
                'getShipmentPackages' => 'order_read',
                'getShipmentPackagesStream' => 'order_read',
                'filterApprovedProductsInventoryAndPrice' => 'product_read',
                'getBatchRequestResult' => 'product_read',
                'updatePriceAndInventory' => 'inventory_price_write',
            ],
        ],
    ],

];
