<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Ekip yonetimi — BACKEND-PLAN.md §4.3. Tek izin: `users.manage`
 * (Sahip + Yonetici).
 *
 * "Son Sahip rolu alinamaz" kurali burada DEGIL controller'da: yetkisi olan
 * bir yonetici bu islemi prensipte yapabilir, engelleyen sey tenant'in
 * sahipsiz kalamamasidir. 403 degil, sebebini soyleyen bir dogrulama hatasi
 * donmeli.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function view(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('users.manage');
    }

    /**
     * `delete` burada "erisimi kaldir" demektir; satir silinmez (bkz.
     * TeamController::destroy). Kimse kendi erisimini kapatamaz — kapatirsa
     * geri acacak kimse kalmayabilir.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->can('users.manage') && $user->isNot($target);
    }
}
