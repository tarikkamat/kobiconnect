<?php

declare(strict_types=1);

namespace App\Enums;

use App\Concerns\HasLabels;

enum DynamicCategoryOperator: string
{
    use HasLabels;

    case Contains = 'contains';
    case NotContains = 'not_contains';
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case Between = 'between';
    case Before = 'before';
    case After = 'after';

    public function label(): string
    {
        return match ($this) {
            self::Contains => 'İçeren',
            self::NotContains => 'İçermeyen',
            self::Equals => 'Eşit olan',
            self::NotEquals => 'Eşit olmayan',
            self::GreaterThan => 'Büyük olan',
            self::LessThan => 'Küçük olan',
            self::Between => 'Arasında olan',
            self::Before => 'Tarihinden önce',
            self::After => 'Tarihinden sonra',
        };
    }
}
