<?php

declare(strict_types=1);

namespace App\Enums;

enum ListingSyncState: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Synced = 'synced';
    case Failed = 'failed';
}
