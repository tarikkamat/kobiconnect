<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Stok ve fiyat, katalog metnini duzenlemekten ayri bir yetkidir: depo rolu
 * stogu gunceller ama urun bilgisine dokunamaz — BACKEND-PLAN.md §4.3.
 */
class ProductVariantPolicy extends CatalogPolicy
{
    public function updateStock(User $user): bool
    {
        return $user->can('stock.manage');
    }

    public function updatePrice(User $user): bool
    {
        return $user->can('catalog.manage');
    }
}
