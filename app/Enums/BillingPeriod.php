<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

enum BillingPeriod: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function advance(CarbonInterface $from): CarbonInterface
    {
        return match ($this) {
            self::Monthly => $from->toImmutable()->addMonth(),
            self::Yearly => $from->toImmutable()->addYear(),
        };
    }
}
