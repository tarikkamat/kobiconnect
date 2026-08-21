<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LicenseStatus;
use App\Events\LicenseExpired;
use App\Events\LicenseExpiring;
use App\Models\License;
use Illuminate\Console\Command;

/**
 * §3.3 — `LicenseExpiring` 7/3/1 gun once, `LicenseExpired` grace period
 * kapandiginda. Gunde bir kez calistirilmalidir; esikler tam tarih esleserek
 * bulundugu icin ayni olay ikinci kez tetiklenmez.
 */
final class CheckExpiringLicenses extends Command
{
    /**
     * @var string
     */
    protected $signature = 'licenses:check-expiring';

    /**
     * @var string
     */
    protected $description = 'Suresi yaklasan lisanslari uyarir, grace period i biten lisanslari kapatir';

    /**
     * Kac gun kala uyarilacak.
     *
     * @var list<int>
     */
    private const WARN_DAYS = [7, 3, 1];

    public function handle(): int
    {
        $expiring = 0;

        foreach (self::WARN_DAYS as $days) {
            $licenses = License::query()
                ->where('status', LicenseStatus::Active)
                ->whereDate('ends_at', now()->addDays($days)->toDateString())
                ->get();

            foreach ($licenses as $license) {
                LicenseExpiring::dispatch($license, ['days_left' => $days]);
                $expiring++;
            }
        }

        $expired = License::query()
            ->where('status', LicenseStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now())
            ->where(fn ($query) => $query->whereNull('grace_until')->orWhere('grace_until', '<', now()))
            ->get();

        foreach ($expired as $license) {
            $license->forceFill(['status' => LicenseStatus::Expired])->save();
            LicenseExpired::dispatch($license);
        }

        $this->info("{$expiring} lisans icin uyari, {$expired->count()} lisans suresi doldu olarak isaretlendi.");

        return self::SUCCESS;
    }
}
