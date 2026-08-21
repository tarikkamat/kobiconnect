<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Kanal baglantilari — BACKEND-PLAN.md §4.3.
 *
 * Tek izin: `channels.manage`. Okuma da ayni izne baglidir cunku bu ekran
 * pazaryeri kimlik bilgilerini tasir; rol listesinde ayri bir `channels.view`
 * yoktur (TenantRoleSeeder). Sahip ve Yonetici disindaki roller giremez.
 *
 * Model parametresi bilerek yok: kurallar satir bazli degil, izin bazli
 * — CatalogPolicy ile ayni desen.
 */
class ChannelConnectionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('channels.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('channels.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('channels.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('channels.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('channels.manage');
    }
}
