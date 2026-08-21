<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LicenseEventContract;
use App\Models\LicenseEvent;

/**
 * §3.3 — her lisans olayi denetim izine yazilir. Bildirim gonderimi baska
 * bir fazda; burada yalnizca kayit tutulur.
 */
final class RecordLicenseEvent
{
    public function handle(LicenseEventContract $event): void
    {
        LicenseEvent::create([
            'license_id' => $event->license()->getKey(),
            'type' => $event->eventType(),
            'payload' => $event->eventPayload(),
        ]);
    }
}
