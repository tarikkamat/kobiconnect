<?php

declare(strict_types=1);

namespace App\Enums;

enum AllocationType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case Remaining = 'remaining';
}
