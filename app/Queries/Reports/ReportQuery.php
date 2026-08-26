<?php

declare(strict_types=1);

namespace App\Queries\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rapor sorgularinin ortak zemini.
 *
 * Iki tur tekrar buraya toplandi:
 *
 *  1. Satir bazli para ifadeleri (ciro, komisyon, adet) dort ayri metotta
 *     birebir ayni `selectRaw` metniydi. Komisyonun oran/yuzde cevrimi tek
 *     yerde durur.
 *  2. `totals` jsonb alanlari PHP'de toplaniyordu: rapor ekrani araliktaki
 *     BUTUN siparis satirlarini bellege cekip donuyordu. Toplama artik
 *     Postgres'te; ekran satir sayisindan bagimsiz calisir.
 *
 * Ifadeler sabit; hicbiri degisken interpolasyonu icermez, yani bu sinifin
 * disindan gelen bir deger ham SQL'e karisamaz.
 */
abstract class ReportQuery
{
    /** Kalem adedi. */
    protected const string ITEM_COUNT = 'COALESCE(SUM(order_lines.quantity), 0)';

    /** Satir tutarlari toplami. */
    protected const string GROSS_SALES = 'COALESCE(SUM(order_lines.unit_price * order_lines.quantity), 0)';

    /** `order_lines.commission` bir ORAN'dir, tutar degil (TRENDYOL.md §4.4.1). */
    protected const string COMMISSION_TOTAL = 'COALESCE(SUM((order_lines.unit_price * order_lines.quantity) * (COALESCE(order_lines.commission, 0) / 100.0)), 0)';

    public function __construct(protected readonly ReportRange $range) {}

    /**
     * Araliga (ve varsa kanala) daraltilmis siparisler.
     */
    protected function orders(): Builder
    {
        return $this->range->orders();
    }

    /**
     * Ayni daraltma, satirlarla birlestirilmis hâli.
     */
    protected function orderLines(): Builder
    {
        return $this->range->apply(
            DB::table('orders')->join('order_lines', 'order_lines.order_id', '=', 'orders.id'),
        );
    }
}
