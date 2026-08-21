<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Password;

/**
 * `PasswordBrokerManager` cozdugu broker'lari BELLEKTE tutar ve broker'in
 * `DatabaseTokenRepository`'si yaratildigi andaki Connection nesnesini saklar.
 * DatabaseTenancyBootstrapper varsayilan baglantiyi tenant'a cevirse de bu
 * kopya eski tenant'i gostermeye devam eder: tenant B'nin sifirlama token'i
 * tenant A'nin `password_reset_tokens` tablosuna yazilir.
 *
 * Octane/RoadRunner altinda worker omru boyunca surer — .ai/rules/providers.md
 * ve FlushPermissionCache ile ayni sizinti sinifi.
 */
final class FlushPasswordBroker
{
    public function __construct(private readonly Application $app) {}

    public function handle(object $event): void
    {
        $this->app->forgetInstance('auth.password');
        $this->app->forgetInstance('auth.password.broker');

        Password::clearResolvedInstance('auth.password');
    }
}
