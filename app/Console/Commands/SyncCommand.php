<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * The scheduler runs on the central connection and knows nothing about tenants,
 * so every sync command starts there and walks into each tenant's schema in
 * turn. `runForMultiple` initialises tenancy per tenant and restores the
 * previous context at the end - jobs dispatched inside the callback therefore
 * carry the right `tenant_id` in their payload (QueueTenancyBootstrapper).
 */
abstract class SyncCommand extends Command
{
    /**
     * ponytail: tek kadans, herkes icin. Tenant basina aralik gerekirse
     * (plan, ayar, kanal) tek degisim noktasi burasidir.
     */
    protected const int DEFAULT_INTERVAL_MINUTES = 15;

    /**
     * @param  callable(Tenant): void  $callback
     */
    protected function forEachTenant(callable $callback): int
    {
        $tenants = Tenant::query()->pluck('id')->map(strval(...));

        /** @var list<string> $only */
        $only = array_filter((array) $this->option('tenant'));

        if ($only !== []) {
            $tenants = $tenants->intersect($only)->values();
        }

        if ($tenants->isEmpty()) {
            $this->info('Senkron edilecek tenant yok.');

            return self::SUCCESS;
        }

        tenancy()->runForMultiple(
            $tenants,
            // One tenant with a broken schema must not take the whole run
            // down; rescue reports it and the walk continues.
            fn (Tenant $tenant) => rescue(fn () => $callback($tenant)),
        );

        return self::SUCCESS;
    }

    protected function intervalMinutes(): int
    {
        return static::DEFAULT_INTERVAL_MINUTES;
    }
}
