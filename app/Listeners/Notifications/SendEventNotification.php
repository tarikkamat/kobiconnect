<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Events\NotificationEventOccurred;
use App\Notifications\NotifyTenantUsers;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * §11.2 — bildirim gonderimi istegin/isin sicak yolunda YAPILMAZ: kuyruga
 * alinir (`default`, §10.1). `ShouldHandleEventsAfterCommit` sart: olay bir
 * transaction icinde dogar (siparis yazimi, stok hareketi) ve commit
 * olmadan gonderilen bildirim henuz var olmayan bir kaydi gosterirdi.
 *
 * Tenant baglami kuyruk payload'indaki `tenant_id` ile geri kurulur
 * (QueueTenancyBootstrapper, §10.1) — burada elle bir sey yapilmaz.
 */
final class SendEventNotification implements ShouldHandleEventsAfterCommit, ShouldQueue
{
    use InteractsWithQueue;

    /**
     * @var string
     */
    public $queue = 'default';

    public function __construct(private readonly NotifyTenantUsers $notify) {}

    public function handle(NotificationEventOccurred $event): void
    {
        ($this->notify)($event->event, $event->payload);
    }
}
