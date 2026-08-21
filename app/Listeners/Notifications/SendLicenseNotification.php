<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Events\LicenseEventContract;
use App\Models\Tenant;
use App\Notifications\NotificationEvent;
use App\Notifications\NotifyTenantUsers;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * §3.3 lisans olaylari → §11.3 bildirimleri.
 *
 * Lisans olaylari CENTRAL baglamda dogar (`licenses:check-expiring` tum
 * tenant'lari tarar) ama bildirim TENANT semasina yazilir. Koprü
 * `$tenant->run()`: baglami acar, TenancyInitialized ile URL varsayilanlarini
 * kurar (ConfigureTenantHost) ve isi bitince onceki baglama geri doner.
 *
 * `App\Events\License*` siniflarina dokunulmadi: dinleme arayuz uzerinden
 * (`Dispatcher::addInterfaceListeners`), tipki RecordLicenseEvent gibi.
 * Olay tipi ile bildirim anahtari zaten birebir esliyor
 * (`Str::snake('LicenseExpiring') === 'license_expiring'`), bu yuzden esleme
 * tablosu yok — haritada karsiligi olmayan olay (aktivasyon, yenileme)
 * sessizce atlanir.
 */
final class SendLicenseNotification implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Kota metrigi kullaniciya kod olarak degil, adiyla gosterilir.
     *
     * @var array<string, string>
     */
    private const array METRIC_LABELS = [
        'products.max' => 'Ürün',
        'orders.per_month' => 'Aylık sipariş',
        'channels.max' => 'Pazaryeri kanalı',
        'seats.max' => 'Kullanıcı',
    ];

    /**
     * @var string
     */
    public $queue = 'default';

    public function __construct(private readonly NotifyTenantUsers $notify) {}

    public function handle(LicenseEventContract $event): void
    {
        $type = NotificationEvent::tryFrom($event->eventType());

        if ($type === null) {
            return;
        }

        $license = $event->license();
        // licenses.tenant_id FK + cascade tasiyor; yetim lisans satiri
        // veritabani seviyesinde imkansiz, bu yuzden null kontrolu yok.
        $tenant = $license->tenant;

        $payload = $event->eventPayload();
        $metric = $payload['metric'] ?? null;

        if (is_string($metric)) {
            $payload['metric'] = self::METRIC_LABELS[$metric] ?? $metric;
        }

        $tenant->run(fn () => ($this->notify)($type, $payload));
    }
}
