<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Support\Facades\Route;

/**
 * BACKEND-PLAN.md §11.2'deki olay haritasi.
 *
 * Olay basina AYRI bir sinif YOK, bilerek: 14 olayin tek farki metin, hedef
 * kitle ve susturma penceresi. Bunlar veridir, davranis degil — hepsi burada
 * durur ve tek bir `App\Events\NotificationEventOccurred` tasiyicisiyla
 * dagitilir. Bildirim olaylari kendi siniflarina
 * sahip; `eventType()` degerleri buradaki `value` ile birebir esler, bu yuzden
 * ayrica esleme tablosu gerekmez.
 *
 * Payload duz bir dizidir (kuyruk payload'inda serilestirilebilir olmali):
 * model degil, id ve gosterilecek metin gecer.
 */
enum NotificationEvent: string
{
    case OrderReceived = 'order_received';
    case OrderCancelled = 'order_cancelled';
    case OrderLineUnmatched = 'order_line_unmatched';
    case StockCriticalLow = 'stock_critical_low';
    case ProductRejected = 'product_rejected';
    case ProductApproved = 'product_approved';
    case SyncFailed = 'sync_failed';
    case ClaimOpened = 'claim_opened';
    case QuestionReceived = 'question_received';
    case WebhookDeliveryFailing = 'webhook_delivery_failing';
    case WebhookDeactivated = 'webhook_deactivated';
    case ConnectionCredentialsInvalid = 'connection_credentials_invalid';

    /**
     * Tercih ekranindaki satir basligi.
     */
    public function label(): string
    {
        return match ($this) {
            self::OrderReceived => 'Yeni sipariş',
            self::OrderCancelled => 'Sipariş iptali',
            self::OrderLineUnmatched => 'Eşleşmeyen sipariş satırı',
            self::StockCriticalLow => 'Kritik stok',
            self::ProductRejected => 'Ürün reddedildi',
            self::ProductApproved => 'Ürün onaylandı',
            self::SyncFailed => 'Senkron hatası',
            self::ClaimOpened => 'Yeni iade talebi',
            self::QuestionReceived => 'Yeni müşteri sorusu',
            self::WebhookDeliveryFailing => 'Webhook teslimi bozuk',
            self::WebhookDeactivated => 'Webhook devre dışı bırakıldı',
            self::ConnectionCredentialsInvalid => 'Bağlantı kimlik bilgisi geçersiz',
        };
    }

    /**
     * Tercih matrisindeki grup basligi.
     */
    public function group(): string
    {
        return match ($this) {
            self::OrderReceived, self::OrderCancelled, self::OrderLineUnmatched,
            self::ClaimOpened, self::QuestionReceived => 'Satış',

            self::StockCriticalLow => 'Envanter',

            self::ProductRejected, self::ProductApproved => 'Katalog',

            self::SyncFailed, self::WebhookDeliveryFailing, self::WebhookDeactivated,
            self::ConnectionCredentialsInvalid => 'Operasyon',

        };
    }

    /**
     * Kullanicinin tercihi yoksa gecerli olan kanallar.
     *
     * E-posta yalnizca "bugun bir insanin mudahale etmesi gereken" olaylarda
     * acik. Sipariş akisi panel ziliyle sinirli: gunde 200 siparis alan bir
     * saticiya 200 e-posta atmak bildirim katmanini komple kapattirir.
     *
     * @return list<NotificationChannel>
     */
    public function defaultChannels(): array
    {
        return match ($this) {
            self::SyncFailed, self::ConnectionCredentialsInvalid, self::WebhookDeactivated,
            self::ProductRejected => [NotificationChannel::Database, NotificationChannel::Mail],

            default => [NotificationChannel::Database],
        };
    }

    /**
     * Bildirimi alabilecek kullaniciyi belirleyen izin.
     *
     * Bugun her olay bir izne baglidir — depo calisanina lisans e-postasi
     * gitmez. Tenant'taki HERKESE gitmesi gereken bir olay eklenirse donus tipi
     * `?string`e genisletilir ve `null` "herkes" anlamina gelir.
     */
    public function permission(): string
    {
        return match ($this) {
            self::SyncFailed, self::WebhookDeliveryFailing, self::WebhookDeactivated,
            self::ConnectionCredentialsInvalid, self::ProductRejected,
            self::ProductApproved => 'channels.manage',

            self::StockCriticalLow => 'stock.manage',

            self::QuestionReceived => 'questions.manage',

            self::ClaimOpened => 'returns.manage',

            self::OrderReceived, self::OrderCancelled, self::OrderLineUnmatched => 'orders.view',
        };
    }

    /**
     * Susturma anahtari ve penceresi (saniye) — `null` ise her olay gonderilir.
     *
     * Gurultu bildirim katmanini oldurur: bir baglanti bozuldugunda drenaj
     * dakikada bir denenir, bir barkod eksikse her siparis satiri ayni seyi
     * soyler. Ilk olay haber verir, pencere boyunca ayni sey susar. Pencere
     * icinde de olan biten `channel_operations` ve `sync_runs` defterlerinde
     * kayitlidir — bildirim bir defter degil, bir uyaridir.
     *
     * @param  array<string, mixed>  $payload
     * @return array{string, int}|null
     */
    public function suppression(array $payload): ?array
    {
        $of = static fn (string $key): string => (string) ($payload[$key] ?? '-');

        return match ($this) {
            // Ayni baglanti icin gunde bir kez. Ikinci hata yeni bilgi degil.
            self::SyncFailed,
            self::WebhookDeliveryFailing => [$this->value.':'.$of('connection_id'), 86400],

            // Kimlik rotasyonunda kisa bir 401 firtinasi BEKLENIR (§11.3);
            // alti saatlik pencere rotasyonu bildirime cevirmeden gercek
            // bozulmayi gunde birkac kez hatirlatir.
            self::ConnectionCredentialsInvalid => [$this->value.':'.$of('connection_id'), 21600],

            // Esik bir kez asilir; stok geri doldurulup tekrar dusene kadar
            // ayni varyant icin susar.
            self::StockCriticalLow => [$this->value.':'.$of('variant_id'), 86400],

            // Eksik barkod her siparis satirinda tekrar eder; kuyruk ekrani
            // zaten tam listeyi tutuyor.
            self::OrderLineUnmatched => [$this->value.':'.$of('connection_id'), 86400],

            self::ProductRejected => [$this->value.':'.$of('connection_id'), 21600],

            // Siparis, iade, soru, urun onayi ve webhook kapanmasi: her biri
            // tekil ve kendi basina anlamli bir olay, susturulmaz.
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function title(array $payload): string
    {
        $of = static fn (string $key, string $fallback = ''): string => is_scalar($payload[$key] ?? null)
            ? (string) $payload[$key]
            : $fallback;

        return match ($this) {
            self::OrderReceived => 'Yeni sipariş: '.$of('order_number', '-'),
            self::OrderCancelled => 'Sipariş iptal edildi: '.$of('order_number', '-'),
            self::OrderLineUnmatched => 'Eşleşmeyen sipariş satırı var',
            self::StockCriticalLow => 'Kritik stok: '.$of('sku', '-'),
            self::ProductRejected => 'Ürün pazaryerinde reddedildi',
            self::ProductApproved => 'Ürün pazaryerinde onaylandı',
            self::SyncFailed => 'Senkron başarısız: '.$of('connection', '-'),
            self::ClaimOpened => 'Yeni iade talebi',
            self::QuestionReceived => 'Yeni müşteri sorusu',
            self::WebhookDeliveryFailing => 'Webhook teslimi başarısız oluyor',
            self::WebhookDeactivated => 'Webhook devre dışı bırakıldı',
            self::ConnectionCredentialsInvalid => 'Bağlantı kimlik bilgisi geçersiz: '.$of('connection', '-'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function body(array $payload): string
    {
        $of = static fn (string $key, string $fallback = '-'): string => is_scalar($payload[$key] ?? null)
            ? (string) $payload[$key]
            : $fallback;

        return match ($this) {
            self::OrderReceived => sprintf(
                '%s kanalından %s numaralı sipariş geldi. Toplam %s.',
                $of('connection'), $of('order_number'), $of('total'),
            ),
            self::OrderCancelled => sprintf(
                '%s numaralı sipariş pazaryerinde iptal edildi. Ayrılan stok serbest bırakılmalı.',
                $of('order_number'),
            ),
            self::OrderLineUnmatched => sprintf(
                '%s kanalından gelen %s sipariş satırı hiçbir varyantla eşleşmedi. Barkodu katalogda tanımlayın; sipariş kaydedildi, veri kaybı yok.',
                $of('connection'), $of('count'),
            ),
            self::StockCriticalLow => sprintf(
                '%s stoğu güvenlik seviyesinin altına indi (kullanılabilir %s, güvenlik stoğu %s). Pazaryerlerine 0 gönderilmeden önce takviye edin.',
                $of('sku'), $of('available'), $of('safety_stock'),
            ),
            self::ProductRejected => sprintf(
                '%s: %s. Hatayı düzeltip ürünü tekrar gönderin.',
                $of('connection'), $of('reason'),
            ),
            self::ProductApproved => sprintf(
                '%s kanalında %s onaylandı ve satışa açıldı.',
                $of('connection'), $of('sku'),
            ),
            self::SyncFailed => sprintf(
                '%s bağlantısında senkron başarısız oldu: %s. Bugün bu bağlantı için tekrar bildirim gönderilmeyecek; güncel durum işlem kuyruğunda.',
                $of('connection'), $of('reason'),
            ),
            self::ClaimOpened => sprintf(
                '%s kanalında %s numaralı sipariş için iade talebi açıldı.',
                $of('connection'), $of('order_number'),
            ),
            self::QuestionReceived => sprintf(
                '%s kanalında yanıt bekleyen bir müşteri sorusu var.',
                $of('connection'),
            ),
            self::WebhookDeliveryFailing => sprintf(
                '%s webhook teslimleri üst üste başarısız oluyor. Kapatılmadan önce uç noktanızı kontrol edin.',
                $of('connection'),
            ),
            self::WebhookDeactivated => sprintf(
                '%s webhook’u pazaryeri tarafından devre dışı bırakıldı. Sipariş akışı yalnızca zamanlanmış çekimle geliyor — yeniden etkinleştirin.',
                $of('connection'),
            ),
            self::ConnectionCredentialsInvalid => sprintf(
                '%s bağlantısı kimlik doğrulamayı geçemedi: %s. Kimlik bilgilerini Uygulama Mağazası ekranından yenileyin.',
                $of('connection'), $of('reason'),
            ),
        };
    }

    /**
     * Bildirimden gidilecek panel yolu (goreli). Route yoksa `null` doner —
     * henuz ekrani olmayan olaylar (soru, iade) bildirimde link gostermez.
     *
     * @param  array<string, mixed>  $payload
     */
    public function url(array $payload): ?string
    {
        $id = static fn (string $key): ?int => is_numeric($payload[$key] ?? null) ? (int) $payload[$key] : null;

        [$name, $parameters] = match ($this) {
            self::OrderReceived, self::OrderCancelled => $id('order_id') === null
                ? ['orders.index', []]
                : ['orders.show', ['order' => $id('order_id')]],

            self::OrderLineUnmatched => ['orders.index', ['unmatched' => 1]],

            self::StockCriticalLow => ['stock.index', []],

            self::ProductRejected, self::ProductApproved => $id('product_id') === null
                ? ['sync.operations.index', []]
                : ['products.show', ['product' => $id('product_id')]],

            self::SyncFailed => ['sync.operations.index', ['status' => 'failed']],

            self::ClaimOpened => ['orders.index', []],

            self::WebhookDeliveryFailing, self::WebhookDeactivated,
            self::ConnectionCredentialsInvalid => ['apps.index', []],

            self::QuestionReceived => ['', []],
        };

        return $name !== '' && Route::has($name)
            ? route($name, $parameters, absolute: false)
            : null;
    }
}
