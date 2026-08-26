<?php

use App\Listeners\ConfigureTenantHost;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\ScopeSessions;

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Authorization Server Path
    |--------------------------------------------------------------------------
    |
    | Her tenant KENDI yetkilendirme sunucusudur: oauth_* tablolari tenant
    | semasindadir ve central'da hic kullanici yoktur. Prefix'teki `{tenant}`
    | bu yuzden sart — InitializeTenancyByPath tenant'in route'un ILK
    | parametresi olmasini bekler (bkz. bootstrap/app.php).
    |
    | Sonuc: issuer https://app.../{tenant}, token'in user_id'si tek anlamli
    | ve tenant silindiginde token'lari da onunla gider.
    |
    */

    'path' => '{tenant}/oauth',

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Panel route'lariyla AYNI yigin. ScopeSessions kozmetik degil: onsuz A
    | tenant'inda oturumu olan biri /B/oauth/authorize adresine gidebilir ve
    | `auth` ayni user id'yi B'nin semasinda cozerek BASKA birinin hesabina
    | token verdirebilirdi.
    |
    */

    'middleware' => [
        'web',
        InitializeTenancyByPath::class,
        ScopeSessions::class,
        ConfigureTenantHost::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
