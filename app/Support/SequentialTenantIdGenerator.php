<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

/**
 * Tenant kimligi = URL'in ilk path segmenti (`/1001/dashboard`).
 *
 * Firma adindan turetilen slug yerine central `tenant_ids` sequence'inden
 * gelen sira numarasi kullaniyoruz. Sequence nextval'i transaction disinda
 * calisir, bu yuzden es zamanli iki kayit ayni numarayi asla alamaz — slug
 * turetmede gereken "bostaki adi ara, carpisirsa sonek ekle" dongusu ve
 * rezerve kelime listesi tumden gereksizlesir: sayisal bir segment `login`
 * veya `register` gibi tek segmentli central route'larla cakisamaz.
 *
 * Sequence 1001'den basliyor (bkz. create_tenant_id_sequence migration).
 */
final class SequentialTenantIdGenerator implements UniqueIdentifierGenerator
{
    /**
     * @param  Tenant  $resource  Stancl arayuzu tipsiz; tenant disinda cagrilmaz.
     */
    public static function generate($resource): string
    {
        return (string) DB::connection('central')->scalar("SELECT nextval('tenant_ids')");
    }
}
