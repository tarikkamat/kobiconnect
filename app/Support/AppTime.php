<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Sunucu ve veritabani UTC, kullanici Europe/Istanbul. Cevrim ve bicimleme
 * sunucuda yapilir (FRONTEND-PLAN §7) — istemci tarih hesabi yapmaz.
 *
 * Bolge adi ve iki bicim daha once dort controller ile dort komutta ayri ayri
 * yaziliydi; bir tanesini degistirmek digerlerini sessizce ayristiriyordu.
 */
final class AppTime
{
    public const string ZONE = 'Europe/Istanbul';

    /**
     * Kullanicinin "simdi"si — gun sinirlari bu saate gore hesaplanir.
     */
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONE);
    }

    public static function parse(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, self::ZONE);
    }

    /**
     * `d.m.Y` — sadece gun gosteren listeler icin.
     */
    public static function date(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'd.m.Y');
    }

    /**
     * `d.m.Y H:i` — saatin de onemli oldugu her yer.
     */
    public static function dateTime(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'd.m.Y H:i');
    }

    /**
     * Model cast'i CarbonImmutable dondurur, ham sorgu satiri string; ikisi de
     * tek noktada normalize edilir. Bos deger `null` doner, "01.01.1970" degil.
     */
    private static function format(DateTimeInterface|string|null $value, string $format): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->timezone(self::ZONE)->format($format);
    }
}
