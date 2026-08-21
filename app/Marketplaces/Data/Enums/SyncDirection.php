<?php

namespace App\Marketplaces\Data\Enums;

/**
 * Direction of a synchronisation run.
 */
enum SyncDirection: string
{
    case Pull = 'pull';

    case Push = 'push';
}
