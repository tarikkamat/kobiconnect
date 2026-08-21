<?php

declare(strict_types=1);

namespace App\Enums;

enum ConnectionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Error = 'error';
}
