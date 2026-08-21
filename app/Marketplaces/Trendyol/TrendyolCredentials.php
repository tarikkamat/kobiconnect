<?php

namespace App\Marketplaces\Trendyol;

use InvalidArgumentException;

/**
 * One seller's Trendyol API identity, as stored in channel_connections.credentials.
 *
 * There is no OAuth, no token exchange, no refresh and no scope: a single key
 * pair opens the seller's whole API surface, and rotation is a manual panel
 * action (TRENDYOL.md 2.2, 2.6).
 */
final readonly class TrendyolCredentials
{
    public function __construct(
        public string $sellerId,
        public string $apiKey,
        public string $apiSecret,
        public string $integrator = 'SelfIntegration',
        public bool $stage = false,
        public string $listingTier = '50k',
    ) {
        if (! ctype_digit($sellerId)) {
            throw new InvalidArgumentException('Trendyol sellerId must be a numeric string.');
        }

        if ($apiKey === '' || $apiSecret === '') {
            throw new InvalidArgumentException('Trendyol apiKey and apiSecret are required.');
        }

        if (preg_match('/^[A-Za-z0-9]{1,30}$/', $integrator) !== 1) {
            throw new InvalidArgumentException('Trendyol integrator name must be alphanumeric and at most 30 characters.');
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public static function fromArray(array $credentials): self
    {
        return new self(
            sellerId: (string) ($credentials['seller_id'] ?? ''),
            apiKey: (string) ($credentials['api_key'] ?? ''),
            apiSecret: (string) ($credentials['api_secret'] ?? ''),
            integrator: (string) ($credentials['integrator'] ?? 'SelfIntegration'),
            stage: (bool) ($credentials['stage'] ?? false),
            listingTier: (string) ($credentials['listing_tier'] ?? '50k'),
        );
    }

    /**
     * Requests that arrive without this header are blocked with 403, which is
     * the single most common silent failure of this integration (TRENDYOL.md 2.3).
     */
    public function userAgent(): string
    {
        return "{$this->sellerId} - {$this->integrator}";
    }
}
