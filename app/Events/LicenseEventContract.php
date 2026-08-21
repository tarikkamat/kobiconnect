<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\License;

/**
 * `license_events` tablosuna yazilacak her lisans olayi bunu uygular.
 *
 * Laravel'in olay dagitici arayuzleri de dinler (`Dispatcher::addInterfaceListeners`),
 * bu yuzden tek bir listener tum lisans olaylarini yakalayabilir.
 */
interface LicenseEventContract
{
    public function license(): License;

    /**
     * `license_events.type` degeri.
     */
    public function eventType(): string;

    /**
     * @return array<string, mixed>
     */
    public function eventPayload(): array;
}
