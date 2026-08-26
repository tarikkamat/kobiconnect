<?php

declare(strict_types=1);

namespace App\Queries;

/**
 * `orders.totals` jsonb alanlarinin SQL karsiliklari.
 *
 * Alanlar hem sayi hem sayisal metin olarak yaziliyor (mapper'lar '449.9000'
 * gibi string uretiyor), bu yuzden `jsonb_typeof` yetmez; duz `::numeric` ise
 * sayiya benzemeyen tek bir degerde butun ekrani dusurur. Desen bir kez burada
 * yazilir ve hem panel hem raporlar ayni ifadeyi kullanir.
 *
 * Ifadelerin hicbiri degisken interpolasyonu icermez: disaridan gelen bir deger
 * ham SQL'e karisamaz.
 */
final class OrderTotals
{
    public const string NET = "(CASE WHEN orders.totals->>'net' ~ '^-?[0-9]+(\\.[0-9]+)?\$' THEN (orders.totals->>'net')::numeric ELSE 0 END)";

    public const string SHIPPING_COST = "(CASE WHEN orders.totals->>'shipping_cost' ~ '^-?[0-9]+(\\.[0-9]+)?\$' THEN (orders.totals->>'shipping_cost')::numeric ELSE 0 END)";

    public const string CARGO_PENALTY = "(CASE WHEN orders.totals->>'cargo_penalty' ~ '^-?[0-9]+(\\.[0-9]+)?\$' THEN (orders.totals->>'cargo_penalty')::numeric ELSE 0 END)";

    public const string LATE_PENALTY = "(CASE WHEN orders.totals->>'late_penalty' ~ '^-?[0-9]+(\\.[0-9]+)?\$' THEN (orders.totals->>'late_penalty')::numeric ELSE 0 END)";

    public const string NET_SUM = 'COALESCE(SUM('.self::NET.'), 0)';

    public const string SHIPPING_COST_SUM = 'COALESCE(SUM('.self::SHIPPING_COST.'), 0)';

    public const string CARGO_PENALTY_SUM = 'COALESCE(SUM('.self::CARGO_PENALTY.'), 0)';

    public const string LATE_PENALTY_SUM = 'COALESCE(SUM('.self::LATE_PENALTY.'), 0)';

    /** Kargo ve gecikme cezasinin toplami. */
    public const string PENALTY_SUM = '('.self::CARGO_PENALTY_SUM.' + '.self::LATE_PENALTY_SUM.')';
}
