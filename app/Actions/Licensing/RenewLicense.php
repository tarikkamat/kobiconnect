<?php

declare(strict_types=1);

namespace App\Actions\Licensing;

use App\Enums\LicenseStatus;
use App\Events\LicenseRenewed;
use App\Models\License;

/**
 * Lisansi bir faturalama donemi uzatir. Grace period icinde yenileme kaldigi
 * yerden devam eder — veri hicbir zaman silinmez (§3.2).
 */
final class RenewLicense
{
    public function __invoke(License $license, int $graceDays = ActivateLicense::GRACE_DAYS): License
    {
        $from = $license->ends_at !== null && $license->ends_at->isFuture()
            ? $license->ends_at
            : now();

        $endsAt = $license->plan->billing_period->advance($from)->toImmutable();

        $license->forceFill([
            'status' => LicenseStatus::Active,
            'ends_at' => $endsAt,
            'grace_until' => $endsAt->addDays($graceDays),
            'cancelled_at' => null,
        ])->save();

        LicenseRenewed::dispatch($license, ['ends_at' => $endsAt->toIso8601String()]);

        return $license;
    }
}
