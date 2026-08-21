<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One seller's Hepsiburada API identity, as stored in
 * channel_connections.credentials.
 *
 * There is no OAuth, no token, no refresh (HEPSIBURADA.md §2.1): plain HTTP
 * Basic where the username is the merchantId UUID and the password is the
 * "Servis Anahtarı" the SELLER generates - and can rotate from their panel
 * without telling us, which is why a 401 has to be reported as "ask for a new
 * key", not as a bug.
 *
 * The `User-Agent` is the second half of the identity, not decoration.
 */
final readonly class HepsiburadaCredentials
{
    public function __construct(
        public string $merchantId,
        public string $serviceKey,
        public string $integrator,
        /** SIT hosts carry a `-sit` suffix and their own credentials (§2.2). */
        public bool $sit = false,
    ) {
        if (! Str::isUuid($merchantId)) {
            throw new InvalidArgumentException('Hepsiburada merchantId must be a UUID.');
        }

        if ($serviceKey === '') {
            throw new InvalidArgumentException('Hepsiburada service key is required.');
        }

        // The bare integrator name is what answered 200 on all three hosts.
        // The widespread `"{merchantId} - {Name}"` template - i.e. Trendyol's
        // shape - is reported to answer 401/403 on several Hepsiburada
        // services, so a value carrying spaces is refused here rather than in
        // production (§2.1).
        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $integrator) !== 1) {
            throw new InvalidArgumentException(
                'Hepsiburada integrator user agent must be the bare integrator username (no spaces).'
            );
        }
    }

    /**
     * The three documented fields are `merchant_id`, `service_key` and
     * `integrator_user_agent` (§2.1). The Trendyol shaped aliases are read as a
     * fallback so a connection stored through the current connection form
     * still binds.
     *
     * @param  array<string, mixed>  $credentials
     */
    public static function fromArray(array $credentials): self
    {
        return new self(
            merchantId: (string) ($credentials['merchant_id'] ?? $credentials['seller_id'] ?? ''),
            serviceKey: (string) ($credentials['service_key'] ?? $credentials['api_secret'] ?? ''),
            integrator: (string) ($credentials['integrator_user_agent'] ?? $credentials['integrator'] ?? ''),
            sit: (bool) ($credentials['sit'] ?? $credentials['stage'] ?? false),
        );
    }

    /**
     * Bare integrator username, e.g. `acme_dev`. Never the Trendyol template.
     */
    public function userAgent(): string
    {
        return $this->integrator;
    }
}
