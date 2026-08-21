<?php

declare(strict_types=1);

namespace App\Enums;

enum RuleScope: string
{
    case Connection = 'connection';
    case Category = 'category';
    case Brand = 'brand';
    case Variant = 'variant';
}
