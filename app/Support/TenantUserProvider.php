<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * users tablosu tenant semasindadir; central baglantida hic yoktur. Tek host =
 * tek session cookie oldugundan, panelde oturumu olan bir kullanici central bir
 * sayfaya (/register, /login) geldiginde session'daki user id central DB'de
 * cozulmeye calisilir ve "relation users does not exist" ile patlar.
 *
 * Cozum tek noktada: tenant baslatilmamisken session/remember-cookie'den user
 * yuklemeyi hic denemeyiz — central'da herkes misafirdir. Kimlik dogrulama
 * yollari (retrieveByCredentials vb.) bilerek korunmadi: onlar zaten tenant
 * baglaminda calismak ZORUNDA (.ai/rules/routes.md) ve central'da sessizce
 * null donmek yerine yuksek sesle hata vermeleri dogru.
 */
class TenantUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return tenant() === null ? null : parent::retrieveById($identifier);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return tenant() === null ? null : parent::retrieveByToken($identifier, $token);
    }
}
