<?php

declare(strict_types=1);

namespace App\Actions\Licensing;

use App\Enums\LicenseStatus;
use App\Events\LicenseActivated;
use App\Models\License;
use App\Models\Plan;
use App\Models\Tenant;
use Carbon\CarbonInterface;

/**
 * Tenant'a plan atar. Yeni kayit da olusturur, plan degisiminde mevcut lisansi
 * da gunceller — bir tenant = bir lisans (§3).
 */
final class ActivateLicense
{
    /**
     * Odeme gecikmesinde verilen salt-okunur pencere.
     */
    public const GRACE_DAYS = 14;

    /**
     * @param  array<string, mixed>  $limitOverrides  Plan varsayilanlarinin uzerine yazilir.
     */
    public function __invoke(
        Tenant $tenant,
        Plan $plan,
        ?CarbonInterface $endsAt = null,
        int $graceDays = self::GRACE_DAYS,
        array $limitOverrides = [],
    ): License {
        $startsAt = now();
        $endsAt = ($endsAt ?? $plan->billing_period->advance($startsAt))->toImmutable();
        $limits = [...$plan->featureMap(), ...$limitOverrides];
        $seats = $limits['seats.max'] ?? 1;

        $license = License::updateOrCreate(
            ['tenant_id' => $tenant->getTenantKey()],
            [
                'plan_id' => $plan->getKey(),
                'status' => LicenseStatus::Active,
                'seats' => is_numeric($seats) ? (int) $seats : 1,
                'limits' => $limits,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'grace_until' => $endsAt->addDays($graceDays),
                'cancelled_at' => null,
            ],
        );

        $license->setRelation('tenant', $tenant);
        $license->setRelation('plan', $plan);
        $license->syncFeatures();

        LicenseActivated::dispatch($license, ['plan' => $plan->code]);

        return $license;
    }
}
