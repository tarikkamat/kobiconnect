<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | This name appears in notifications and in the Horizon UI. Unique names
    | can be useful while running multiple instances of Horizon within an
    | application, allowing you to identify the Horizon you're viewing.
    |
    */

    'name' => env('HORIZON_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug((string) env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Dashboard Operators
    |--------------------------------------------------------------------------
    |
    | Horizon, central domain'de yasar ve orada kullanici YOKTUR (auth tenant
    | semasindadir — .ai/rules/routes.md). Bu yuzden erisim rol degil, acik bir
    | operator listesidir; `Gate::define('viewHorizon')` bunu okur.
    |
    */

    'operators' => array_filter(explode(',', (string) env('HORIZON_OPERATORS', ''))),

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        // Stok ve fiyat gecikmesi dogrudan satisi etkiler: dar tutulur.
        'redis:push-inventory' => 30,
        'redis:webhooks' => 60,
        'redis:sync-orders' => 300,
        'redis:sync-products' => 600,
        'redis:tenant-provisioning' => 600,
        'redis:default' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Silencing a job will instruct Horizon to not place the job in the list
    | of completed jobs within the Horizon dashboard. This setting may be
    | used to fully remove any noisy jobs from the completed jobs list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 64,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    /*
    | Kuyruk oncelikleri BACKEND-PLAN.md 10.1'den. Her kuyruk kendi
    | supervisor'unda: bir katalog senkronu, bir stok push'unu asla bekletmez.
    |
    | Timeout zinciri: is timeout < supervisor timeout < queue.retry_after (90).
    | Bu yuzden hicbir supervisor 90 saniyeyi gecmez.
    |
    | Kuyruklar tenant-aware'dir: QueueTenancyBootstrapper payload'a `tenant_id`
    | ekler ve JobProcessing'de tenancy'yi baslatir. Central kalmasi gereken bir
    | connection icin `queue.connections.<ad>.central = true` kullanilir.
    */

    'defaults' => [

        // Stok/fiyat push — dusuk gecikme kritik, en yuksek oncelik.
        'push-inventory' => [
            'connection' => 'redis',
            'queue' => ['push-inventory'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],

        // Gelen webhook isleme. Trendyol basarisiz teslimati 5 dakikada bir
        // tekrarlar ve 13 hatada webhook'u kapatir — hizli cevap sarttir.
        'webhooks' => [
            'connection' => 'redis',
            'queue' => ['webhooks'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 6,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 30,
            'nice' => 0,
        ],

        'sync-orders' => [
            'connection' => 'redis',
            'queue' => ['sync-orders'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 4,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 192,
            'tries' => 3,
            'timeout' => 85,
            'nice' => 5,
        ],

        // Katalog senkronu ve batch polling ayni bütcede: ikisi de pazaryerinin
        // Read kotasini yer (TRENDYOL.md 6.6).
        'sync-products' => [
            'connection' => 'redis',
            'queue' => ['sync-products'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 4,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 192,
            'tries' => 3,
            'timeout' => 85,
            'nice' => 5,
        ],

        // Sema yaratma + migration. Dusuk oncelik, uzun is.
        //
        // ponytail: 85 saniye, `queue.connections.redis.retry_after` 90 oldugu
        // icin tavan bu. Gercek uzun timeout, raporda verilen `redis-long`
        // connection'i (retry_after 900, central => true) eklendiginde
        // 'connection' => 'redis-long', 'timeout' => 600 ile acilir.
        'tenant-provisioning' => [
            'connection' => 'redis',
            'queue' => ['tenant-provisioning'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 85,
            'nice' => 10,
        ],

        // Bildirimler, raporlar.
        'default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 10,
        ],

    ],

    'environments' => [
        'production' => [
            /*
             * Tavanlar HEDEF KUTUYA gore olceklenir, istege gore degil.
             * Referans: 4 core / 8 GB VDS, ayni kutuda PostgreSQL + Redis +
             * Octane calisiyor. Worker basina ~90 MB gercek kullanim.
             *
             * `balance: auto` bu sayilari TAVAN olarak kullanir; bos zamanda
             * minProcesses'e iner, ama bir katalog ice aktarma patlamasinda
             * tavana KADAR acilir. Yani tavan, kutunun kaldirabilecegi seydir.
             *
             * Daha buyuk kutuya gecince HORIZON_MAX_* degerlerini yukselt;
             * kodu degistirme.
             */
            'push-inventory' => [
                'maxProcesses' => env('HORIZON_MAX_PUSH_INVENTORY', 4),
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],
            'webhooks' => ['maxProcesses' => env('HORIZON_MAX_WEBHOOKS', 3), 'balanceCooldown' => 3],
            'sync-orders' => ['maxProcesses' => env('HORIZON_MAX_SYNC_ORDERS', 2), 'balanceCooldown' => 3],
            'sync-products' => ['maxProcesses' => env('HORIZON_MAX_SYNC_PRODUCTS', 2), 'balanceCooldown' => 3],
            'tenant-provisioning' => ['maxProcesses' => env('HORIZON_MAX_PROVISIONING', 1)],
            'default' => ['maxProcesses' => env('HORIZON_MAX_DEFAULT', 2)],
        ],

        'local' => [
            'push-inventory' => ['maxProcesses' => 2],
            'webhooks' => ['maxProcesses' => 1],
            'sync-orders' => ['maxProcesses' => 1],
            'sync-products' => ['maxProcesses' => 1],
            'tenant-provisioning' => ['maxProcesses' => 1],
            'default' => ['maxProcesses' => 1],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    |
    | The following list of directories and files will be watched when using
    | the `horizon:listen` command. Whenever any directories or files are
    | changed, Horizon will automatically restart to apply all changes.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
