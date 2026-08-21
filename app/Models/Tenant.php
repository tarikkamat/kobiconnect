<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Bir tenant = bir lisans. Kisit veritabani seviyesinde de var
     * (licenses.tenant_id unique + FK).
     *
     * @return HasOne<License, $this>
     */
    public function license(): HasOne
    {
        return $this->hasOne(License::class);
    }
}
