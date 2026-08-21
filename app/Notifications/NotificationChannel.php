<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Bildirim kanallari — BACKEND-PLAN.md §11.3.
 *
 * Deger, Laravel'in kanal adiyla AYNIDIR; `via()` bu diziyi oldugu gibi doner.
 */
enum NotificationChannel: string
{
    case Database = 'database';
    case Mail = 'mail';
    case Broadcast = 'broadcast';

    public function label(): string
    {
        return match ($this) {
            self::Database => 'Panel',
            self::Mail => 'E-posta',
            self::Broadcast => 'Canlı',
        };
    }

    /**
     * ponytail: broadcast'in yapisi hazir (`toBroadcast()` var, tercih matrisinde
     * kolonu var) ama `BROADCAST_CONNECTION=log` oldugu surece secilemez —
     * secilebilir olsaydi kullaniciya hicbir zaman ulasmayan bir kanal
     * vaat ederdik. Reverb/Pusher yapilandirildigi an bu kontrol dogru doner,
     * baska hicbir sey degismez.
     */
    public function isAvailable(): bool
    {
        if ($this !== self::Broadcast) {
            return true;
        }

        $driver = config('broadcasting.default');

        return is_string($driver) && ! in_array($driver, ['log', 'null'], true);
    }

    /**
     * @return list<self>
     */
    public static function available(): array
    {
        return array_values(array_filter(self::cases(), fn (self $channel): bool => $channel->isAvailable()));
    }
}
