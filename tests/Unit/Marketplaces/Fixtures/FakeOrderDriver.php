<?php

namespace Tests\Unit\Marketplaces\Fixtures;

use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Contracts\SupportsOrderSync;
use App\Marketplaces\Data\OrderData;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Support\Capability;
use DateTimeImmutable;

/**
 * A driver that only pulls orders, used to assert capability detection.
 */
final class FakeOrderDriver implements MarketplaceDriver, SupportsOrderSync
{
    public function identifier(): string
    {
        return 'fake';
    }

    public function displayName(): string
    {
        return 'Fake Marketplace';
    }

    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return Capability::supportedBy($this);
    }

    /**
     * @return list<array{name: string, label: string, type: 'text'|'secret'|'select'|'checkbox', rules: list<string>, help?: string, options?: list<string>, default?: string, identity?: bool}>
     */
    public function credentialFields(): array
    {
        return [];
    }

    /**
     * @return PullPage<OrderData>
     */
    public function pullOrders(?DateTimeImmutable $updatedSince = null, ?string $cursor = null): PullPage
    {
        return new PullPage([]);
    }

    public function pullOrder(string $remoteOrderId): ?OrderData
    {
        return null;
    }
}
