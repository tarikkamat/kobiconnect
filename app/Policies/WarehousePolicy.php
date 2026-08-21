<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Depo yonetimi — BACKEND-PLAN.md §4.3.
 *
 * Okuma `catalog.view` ister (bes rolun hepsinde var): muhasebeci depo
 * listesini gorebilmeli ama degistirememeli — aksiyon gizlenmez, devre disi
 * birakilir (FRONTEND-PLAN §6). Yazma `stock.manage` ister; Sahip, Yonetici
 * ve Depo rollerinde vardir.
 *
 * "Son depo silinemez" / "varsayilan depo silinemez" kurallari burada DEGIL
 * controller'da: bunlar yetki degil is kuralidir ve kullaniciya 403 yerine
 * anlamli bir dogrulama mesaji donmeli.
 */
class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('catalog.view');
    }

    public function view(User $user): bool
    {
        return $user->can('catalog.view');
    }

    public function create(User $user): bool
    {
        return $user->can('stock.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('stock.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('stock.manage');
    }
}
