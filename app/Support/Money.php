<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Number;

/**
 * Para ve oran sunucuda bicimlenir — FRONTEND-PLAN §7.
 *
 * `Number::currency($x, 'TRY', 'tr')` uc controller'da altmisi askin kez, her
 * seferinde `(string)` cast'iyle birlikte yaziliyordu. Yerel ve para birimi
 * varsayilani tek yerde durur.
 */
final class Money
{
    public static function format(float $amount, string $currency = 'TRY'): string
    {
        return (string) Number::currency($amount, $currency, 'tr');
    }

    public static function percent(float $value, int $precision = 1): string
    {
        return (string) Number::percentage($value, precision: $precision, locale: 'tr');
    }
}
