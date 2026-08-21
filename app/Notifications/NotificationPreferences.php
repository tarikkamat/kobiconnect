<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * `notification_preferences` tablosunun tek okuma/yazma noktasi.
 *
 * ponytail: Eloquent modeli YOK — tablo iki sutunluk bir tercih defteri, iliski
 * ve olay hayati tasimiyor. Sorgu olusturucu yeterli; `app/Models` altina bir
 * dosya daha koymak hicbir sey kazandirmazdi.
 *
 * Satirin YOKLUGU ile BOS dizi farklidir: satir yoksa olayin varsayilan
 * kanallari gecerlidir, bos dizi ise "bu olaydan hic haber verme" demektir.
 */
final class NotificationPreferences
{
    /**
     * Kullanicinin bu olay icin secili kanallari.
     *
     * @return list<string> Laravel kanal adlari (`via()` bunu oldugu gibi doner)
     */
    public static function channelsFor(User $user, NotificationEvent $event): array
    {
        $stored = DB::table('notification_preferences')
            ->where('user_id', $user->getKey())
            ->where('event', $event->value)
            ->value('channels');

        $channels = $stored === null
            ? array_map(static fn (NotificationChannel $channel): string => $channel->value, $event->defaultChannels())
            : self::decode($stored);

        // Yapilandirilmamis bir kanal (ornegin broadcast) tercihte kalmis
        // olabilir; ulasmayacagi bir kanala gondermeyiz.
        return array_values(array_filter(
            $channels,
            static fn (string $channel): bool => NotificationChannel::tryFrom($channel)?->isAvailable() === true,
        ));
    }

    /**
     * Tercih ekranini besleyen matris: her olay icin secili kanallar.
     *
     * @return array<string, list<string>>
     */
    public static function matrixFor(User $user): array
    {
        /** @var array<string, string> $stored */
        $stored = DB::table('notification_preferences')
            ->where('user_id', $user->getKey())
            ->pluck('channels', 'event')
            ->all();

        $matrix = [];

        foreach (NotificationEvent::cases() as $event) {
            $matrix[$event->value] = array_key_exists($event->value, $stored)
                ? self::decode($stored[$event->value])
                : array_map(static fn (NotificationChannel $channel): string => $channel->value, $event->defaultChannels());
        }

        return $matrix;
    }

    /**
     * Ekrandan gelen matrisin TAMAMINI yazar: gonderilmeyen olay bos kanal
     * listesi demektir, sessizce varsayilana donmez.
     *
     * @param  array<string, list<string>>  $matrix
     */
    public static function save(User $user, array $matrix): void
    {
        $now = now();

        $rows = array_map(static fn (NotificationEvent $event): array => [
            'user_id' => $user->getKey(),
            'event' => $event->value,
            'channels' => (string) json_encode(array_values(array_intersect(
                $matrix[$event->value] ?? [],
                array_map(static fn (NotificationChannel $channel): string => $channel->value, NotificationChannel::available()),
            ))),
            'created_at' => $now,
            'updated_at' => $now,
        ], NotificationEvent::cases());

        DB::table('notification_preferences')->upsert($rows, ['user_id', 'event'], ['channels', 'updated_at']);
    }

    /**
     * @return list<string>
     */
    private static function decode(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded)
            ? array_values(array_filter($decoded, 'is_string'))
            : [];
    }
}
