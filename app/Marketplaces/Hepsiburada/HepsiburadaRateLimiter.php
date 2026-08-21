<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada;

use App\Marketplaces\Support\Exceptions\MarketplaceException;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Sleep;

/**
 * Hepsiburada's budget is per EGRESS IP, not per seller (HEPSIBURADA.md H11,
 * §7.2). One VDS is one exit IP, so in a multi tenant install every tenant
 * spends from the SAME bucket - one tenant's nightly category crawl can starve
 * everyone else.
 *
 * Two consequences shape this class, and both are inversions of
 * TrendyolRateLimiter:
 *
 *  1. The bucket key carries NO tenant and NO merchant. It is `(host)` only.
 *     Trendyol's `(sellerId, endpoint)` shape would hand each tenant its own
 *     180/min and blow past the real limit by however many tenants we host.
 *  2. It counts through `global_cache()`. `Illuminate\Cache\RateLimiter` is a
 *     cache client, and under `CacheTenancyBootstrapper` every cache key is
 *     tagged with the tenant id - which would silo the counters per tenant and
 *     produce exactly the multiplication described above (BACKEND-PLAN §7.6).
 *
 * Nothing is read from the response: measured 200s carry no `X-RateLimit-*` and
 * no `Retry-After` (§7.3), so the budget is ours and is spent BEFORE the
 * request, never repaired after a 429.
 *
 * ⚠️ The 180/min figure comes from the seller's own note; the official pages
 * publish much higher per-second limits (§7.1). The strict number is the safe
 * ceiling, so it is the default - and it is configuration, not a constant,
 * because the real threshold still has to be measured (Ek A #9).
 */
final class HepsiburadaRateLimiter
{
    public function __construct(private readonly Repository $config) {}

    /**
     * Blocks until the host bucket has room, then spends one request from it.
     *
     * @throws MarketplaceException when the budget does not free up in time
     */
    public function acquire(HepsiburadaService $service): void
    {
        $limiter = $this->limiter();
        $key = "hepsiburada:{$service->value}";
        $limit = $this->integer("rate_limits.services.{$service->value}.limit", $this->integer('rate_limits.limit', 180));
        $decaySeconds = $this->integer("rate_limits.services.{$service->value}.seconds", $this->integer('rate_limits.seconds', 60));
        $maxWaits = $this->integer('rate_limits.max_waits', 12);

        for ($wait = 0; $wait <= $maxWaits; $wait++) {
            if (! $limiter->tooManyAttempts($key, $limit)) {
                $limiter->hit($key, $decaySeconds);

                return;
            }

            Sleep::for(max(1, $limiter->availableIn($key)))->seconds();
        }

        throw new MarketplaceException(
            "Hepsiburada rate limit budget for host [{$service->value}] did not free up after {$maxWaits} waits."
        );
    }

    /**
     * The untenanted cache manager. `global_cache()` is stancl/tenancy's
     * escape hatch for exactly this: a counter that must be shared by every
     * tenant instead of tagged per tenant.
     */
    private function limiter(): RateLimiter
    {
        $cache = global_cache();

        if (! $cache instanceof CacheManager) {
            throw new MarketplaceException(
                'global_cache() did not resolve a cache manager; the Hepsiburada rate limit budget must never be tenant scoped.'
            );
        }

        return new RateLimiter($cache->store());
    }

    private function integer(string $key, int $default): int
    {
        $value = $this->config->get("marketplaces.hepsiburada.{$key}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
