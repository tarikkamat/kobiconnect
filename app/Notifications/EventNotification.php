<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * §11.2'deki 15 olayin TEK bildirim sinifi.
 *
 * ponytail: olay basina bir Notification sinifi yazmiyoruz — hepsinin govdesi
 * "baslik, metin, bir link" ve bu ucu olayin kendisi (NotificationEvent)
 * uretiyor. `ShouldQueue` de YOK: bu bildirimi gonderen listener zaten kuyrukta
 * calisiyor, ikinci bir job devri hicbir sey kazandirmaz.
 */
final class EventNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly NotificationEvent $event,
        public readonly array $payload = [],
    ) {}

    /**
     * Kanallar kullanicinin tercihinden gelir — §11.3.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User
            ? NotificationPreferences::channelsFor($notifiable, $this->event)
            : [];
    }

    /**
     * `notifications.data` — panel zili ve bildirim sayfasi bunu okur.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event->value,
            'title' => $this->event->title($this->payload),
            'body' => $this->event->body($this->payload),
            'url' => $this->event->url($this->payload),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->event->url($this->payload);

        $message = (new MailMessage)
            ->subject($this->event->title($this->payload))
            ->greeting('Merhaba,')
            ->line($this->event->body($this->payload));

        if ($url !== null) {
            $message->action('Panelde aç', url($url));
        }

        return $message
            ->line('Bu bildirimi almak istemiyorsanız Ayarlar → Bildirim Tercihleri ekranından kapatabilirsiniz.')
            ->salutation('KobiConnect');
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
