<?php

declare(strict_types=1);

namespace Tests\Feature\Sync\Fixtures;

use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Contracts\SupportsInventorySync;
use App\Marketplaces\Contracts\SupportsProductSync;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\ProductData;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Data\StockData;
use App\Marketplaces\Support\Capability;
use App\Support\Sync\BindsCredentials;
use DateTimeImmutable;
use RuntimeException;

/**
 * A marketplace that does exactly what the engine is told it does: accepts a
 * batch now and reveals item results later. The engine is marketplace
 * independent, so this is the only marketplace its tests need.
 */
final class FakePushDriver implements BindsCredentials, MarketplaceDriver, SupportsInventorySync, SupportsProductSync
{
    /** @var list<list<StockData>> */
    public array $stockPushes = [];

    /** @var array<string, mixed> */
    public array $credentials = [];

    public ?RuntimeException $failWith = null;

    public function __construct(
        public PushResult $pushResult = new PushResult(accepted: true, remoteBatchId: 'batch-1'),
        public PushResult $batchResult = new PushResult(accepted: true, remoteBatchId: 'batch-1'),
    ) {}

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
     * @param  array<string, mixed>  $credentials
     */
    public function withCredentials(array $credentials): MarketplaceDriver
    {
        $this->credentials = $credentials;

        return $this;
    }

    /**
     * @param  list<StockData>  $stock
     */
    public function pushStock(array $stock, MappingContext $context): PushResult
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        $this->stockPushes[] = $stock;

        return $this->pushResult;
    }

    /**
     * @return PullPage<StockData>
     */
    public function pullStock(?string $cursor = null): PullPage
    {
        return new PullPage([]);
    }

    /**
     * @param  list<ProductData>  $products
     */
    public function createProducts(array $products, MappingContext $context): PushResult
    {
        return $this->pushResult;
    }

    /**
     * @param  list<ProductData>  $products
     */
    public function updateProducts(array $products, MappingContext $context): PushResult
    {
        return $this->pushResult;
    }

    /**
     * @return PullPage<ProductData>
     */
    public function pullProducts(?DateTimeImmutable $updatedSince = null, ?string $cursor = null): PullPage
    {
        return new PullPage([]);
    }

    public function productPushResult(string $remoteBatchId): PushResult
    {
        return $this->batchResult;
    }
}
