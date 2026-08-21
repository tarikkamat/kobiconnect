<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Pennant Store
    |--------------------------------------------------------------------------
    |
    | Here you will specify the default store that Pennant should use when
    | storing and resolving feature flag values. Pennant ships with the
    | ability to store flag values in an in-memory array or database.
    |
    | Supported: "array", "database"
    |
    */

    'default' => env('PENNANT_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Pennant Stores
    |--------------------------------------------------------------------------
    |
    | Here you may configure each of the stores that should be available to
    | Pennant. These stores shall be used to store resolved feature flag
    | values - you may configure as many as your application requires.
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
        ],

        /*
         * Baglanti acikca 'central' olmalidir: tenant context'inde varsayilan
         * baglanti 'tenant' semasina isaret eder ve feature satirlari tenant
         * semasinda aranir. Lisans/feature verisi central'da yasar.
         */
        'database' => [
            'driver' => 'database',
            'connection' => 'central',
            'table' => 'features',
        ],

    ],
];
