<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * The scheduler runs on the central connection and knows nothing about tenants,
 * so every sync command starts there and walks into each tenant's schema in
 * turn. `runForMultiple` initialises tenancy per tenant and restores the
 * previous context at the end - jobs dispatched inside the callback therefore
 * carry the right `tenant_id` in their payload (QueueTenancyBootstrapper).
 *
 * The licence is the gate and it is read *before* entering the schema: an
 * expired or suspended licence stops synchronisation and never touches data
 * (BACKEND-PLAN 3.2). Grace period counts as stopped - it is read only mode,
 * and a push is a write.
 */
abstract class SyncCommand extends Command
{
    /**
     * Nothing in `licenses.limits` means the default cadence.
     */
    protected const int DEFAULT_INTERVAL_MINUTES = 15;

    /**
     * @param  callable(Tenant, License): void  $callback
     */
    protected function forLicensedTenants(callable $callback): int
    {
        $licenses = License::query()
            ->where('status', LicenseStatus::Active)
            ->get()
            ->filter(static fn (License $license): bool => $license->isActive())
            ->keyBy('tenant_id');

        /** @var list<string> $only */
        $only = array_filter((array) $this->option('tenant'));

        if ($only !== []) {
            $licenses = $licenses->only($only);
        }

        if ($licenses->isEmpty()) {
            $this->info('Senkron edilecek aktif lisansli tenant yok.');

            return self::SUCCESS;
        }

        tenancy()->runForMultiple(
            $licenses->keys()->map(strval(...)),
            function (Tenant $tenant) use ($licenses, $callback): void {
                $license = $licenses->get($tenant->getTenantKey());

                if (! $license instanceof License) {
                    return;
                }

                // One tenant with a broken schema must not take the whole run
                // down; rescue reports it and the walk continues.
                rescue(fn () => $callback($tenant, $license));
            },
        );

        return self::SUCCESS;
    }

    /**
     * How often this tenant's plan allows a marketplace to be polled.
     */
    protected function intervalMinutes(License $license): int
    {
        return max(1, $license->limit('sync.interval_minutes') ?? static::DEFAULT_INTERVAL_MINUTES);
    }
}
