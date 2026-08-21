<?php

namespace App\Marketplaces\Trendyol;

use App\Marketplaces\Support\Exceptions\MarketplaceException;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Sleep;

/**
 * The two axis budget from TRENDYOL.md 7.7 and BACKEND-PLAN.md 9.1:
 *
 *   layer 1 - (sellerId, endpoint): 50 requests / 10 seconds, on every endpoint
 *   layer 2 - (sellerId, serviceGroup): a minute quota sized by the seller's
 *             product listing tier
 *
 * Every number is read from config/marketplaces.php and none of them is baked
 * into this class: on 14 September 2026 Trendyol switches the product limits
 * from per endpoint to per service group and updatePriceAndInventory goes from
 * unlimited to 350-2000/min (TRENDYOL.md 7.3, 12).
 *
 * Trendyol documents no rate limit response header at all - `Retry-After` and
 * `X-RateLimit-*` return zero matches across their docs (TRENDYOL.md 7.6) - so
 * nothing here reads one. The budget is ours and is spent before the request,
 * not repaired after a 429.
 *
 * The bucket key always carries the sellerId: the limits are per seller, and in
 * a multi tenant install one noisy tenant must not starve the others.
 */
final class TrendyolRateLimiter
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly Repository $config,
    ) {}

    /**
     * Blocks until both buckets have room, then spends one request from each.
     *
     * @throws MarketplaceException when the budget does not free up in time
     */
    public function acquire(string $sellerId, string $endpoint, string $listingTier): void
    {
        $buckets = $this->buckets($sellerId, $endpoint, $listingTier);
        $maxWaits = $this->integer('rate_limits.max_waits', 12);

        for ($wait = 0; $wait <= $maxWaits; $wait++) {
            $seconds = 0;

            foreach ($buckets as [$key, $max]) {
                if ($this->limiter->tooManyAttempts($key, $max)) {
                    $seconds = max($seconds, $this->limiter->availableIn($key), 1);
                }
            }

            if ($seconds === 0) {
                foreach ($buckets as [$key, , $decaySeconds]) {
                    $this->limiter->hit($key, $decaySeconds);
                }

                return;
            }

            Sleep::for($seconds)->seconds();
        }

        throw new MarketplaceException(
            "Trendyol rate limit budget for seller [{$sellerId}] endpoint [{$endpoint}] did not free up after {$maxWaits} waits."
        );
    }

    /**
     * Which minute quota an endpoint spends from. Unmapped endpoints fail loudly
     * rather than silently borrowing another group's budget.
     */
    public function serviceGroup(string $endpoint): string
    {
        $group = $this->config->get("marketplaces.trendyol.rate_limits.endpoints.{$endpoint}");

        if (! is_string($group) || $group === '') {
            throw new MarketplaceException(
                "Trendyol endpoint [{$endpoint}] has no service group in config/marketplaces.php."
            );
        }

        return $group;
    }

    /**
     * @return list<array{0: string, 1: int, 2: int}> [key, maxAttempts, decaySeconds]
     */
    private function buckets(string $sellerId, string $endpoint, string $listingTier): array
    {
        $group = $this->serviceGroup($endpoint);

        return [
            [
                "trendyol:{$sellerId}:endpoint:{$endpoint}",
                $this->integer('rate_limits.endpoint.limit', 50),
                $this->integer('rate_limits.endpoint.seconds', 10),
            ],
            [
                "trendyol:{$sellerId}:group:{$group}",
                $this->groupLimit($group, $listingTier),
                60,
            ],
        ];
    }

    /**
     * The published minute quota for the group, cut down to the headroom we aim
     * for: Trendyol narrows limits by announcement and their window is not
     * aligned with our clock (TRENDYOL.md 7.7).
     */
    private function groupLimit(string $group, string $listingTier): int
    {
        $tiers = $this->config->get("marketplaces.trendyol.rate_limits.groups.{$group}");

        if (! is_array($tiers)) {
            throw new MarketplaceException(
                "Trendyol service group [{$group}] has no tier limits in config/marketplaces.php."
            );
        }

        $limit = $tiers[$listingTier] ?? null;

        if (! is_numeric($limit)) {
            throw new MarketplaceException(
                "Trendyol service group [{$group}] has no limit for listing tier [{$listingTier}]."
            );
        }

        $headroom = $this->config->get('marketplaces.trendyol.rate_limits.headroom', 0.7);

        return max(1, (int) floor((int) $limit * (is_numeric($headroom) ? (float) $headroom : 0.7)));
    }

    private function integer(string $key, int $default): int
    {
        $value = $this->config->get("marketplaces.trendyol.{$key}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
