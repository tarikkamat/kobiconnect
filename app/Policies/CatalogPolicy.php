<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Katalog varliklarinin ortak yetki kurallari — BACKEND-PLAN.md §4.3.
 *
 * Okuma `catalog.view`, yazma `catalog.manage` ister. Bes rolun hepsinde
 * `catalog.view` var; `catalog.manage` yalnizca Sahip ve Yonetici'de.
 *
 * Model parametresi bilerek yok: kurallar satir bazli degil, izin bazli.
 * PHP fazladan argumani yok sayar, Gate yine de modeli gecirir.
 */
abstract class CatalogPolicy
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
        return $user->can('catalog.manage');
    }

    public function update(User $user): bool
    {
        return $user->can('catalog.manage');
    }

    public function delete(User $user): bool
    {
        return $user->can('catalog.manage');
    }
}
