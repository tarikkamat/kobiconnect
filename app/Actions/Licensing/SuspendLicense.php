<?php

declare(strict_types=1);

namespace App\Actions\Licensing;

use App\Enums\LicenseStatus;
use App\Events\LicenseSuspended;
use App\Models\License;

final class SuspendLicense
{
    public function __invoke(License $license, ?string $reason = null): License
    {
        $license->forceFill(['status' => LicenseStatus::Suspended])->save();

        LicenseSuspended::dispatch($license, $reason === null ? [] : ['reason' => $reason]);

        return $license;
    }
}
