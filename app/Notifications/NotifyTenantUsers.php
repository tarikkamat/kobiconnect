<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/**
 * Bir olayi tenant'in ilgili kullanicilarina dagitir. Tenant baglaminda
 * cagrilmak ZORUNDADIR (`users` ve `notifications` tenant semasindadir).
 *
 * Iki filtre var ve ikisi de urunun yasamasi icin:
 *
 *  1. **Susturma.** Ayni olay penceresi icinde ikinci bir bildirim
 *     gonderilmez (NotificationEvent::suppression). Cache anahtari
 *     `CacheTenancyBootstrapper` sayesinde zaten tenant'a ozeldir; `add()`
 *     atomiktir, iki paralel worker ayni uyariyi iki kez atamaz.
 *  2. **Yetki.** Olayin ilgilendirdigi izne sahip olmayan kullaniciya hic
 *     gonderilmez — depo calisani lisans e-postasi almaz.
 */
final class NotifyTenantUsers
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(NotificationEvent $event, array $payload = []): void
    {
        if ($this->suppressed($event, $payload)) {
            return;
        }

        $permission = $event->permission();

        $recipients = User::query()
            ->with('roles.permissions')
            ->get()
            ->filter(fn (User $user): bool => $user->can($permission));

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new EventNotification($event, $payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function suppressed(NotificationEvent $event, array $payload): bool
    {
        $suppression = $event->suppression($payload);

        if ($suppression === null) {
            return false;
        }

        [$key, $seconds] = $suppression;

        return ! Cache::add('notify:'.$key, true, $seconds);
    }
}
