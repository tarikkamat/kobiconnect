<?php

namespace App\Marketplaces\Trendyol;

use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Contracts\SupportsBrandCatalog;
use App\Marketplaces\Contracts\SupportsCategoryCatalog;
use App\Marketplaces\Contracts\SupportsInventorySync;
use App\Marketplaces\Contracts\SupportsOrderSync;
use App\Marketplaces\Contracts\SupportsPriceSync;
use App\Marketplaces\Data\AttributeData;
use App\Marketplaces\Data\BrandData;
use App\Marketplaces\Data\CategoryNodeData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\OrderData;
use App\Marketplaces\Data\PriceData;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Data\StockData;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Trendyol\Mappers\AttributeMapper;
use App\Marketplaces\Trendyol\Mappers\BrandMapper;
use App\Marketplaces\Trendyol\Mappers\CategoryMapper;
use App\Marketplaces\Trendyol\Mappers\OrderMapper;
use App\Support\Sync\BindsCredentials;
use App\Support\Sync\ReadsBatchResults;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Trendyol reference data (brands, category tree, category attributes), order
 * pulling, and the stock and price push.
 *
 * Products, claims, questions and webhooks are later phases and are
 * deliberately absent - `capabilities()` is derived from the contracts this
 * class implements, so it cannot claim more than it does. In particular
 * `product_sync` stays unimplemented until the attribute payload contradiction
 * of TRENDYOL.md 9.6 / Ek A #1 is settled on stage; reading a batch result does
 * not require it, which is what ReadsBatchResults is for.
 *
 * Every reference read is cached: those endpoints allow 50 requests a minute
 * and getCategoryAttributeValues fans out one call per (category, attribute)
 * pair, so a cache is not an optimisation here (TRENDYOL.md K10,
 * BACKEND-PLAN 7.6). Order reads are never cached - they are the moving part.
 */
final class TrendyolDriver implements BindsCredentials, MarketplaceDriver, ReadsBatchResults, SupportsBrandCatalog, SupportsCategoryCatalog, SupportsInventorySync, SupportsOrderSync, SupportsPriceSync
{
    /**
     * Items Trendyol accepts in one price-and-inventory batch (TRENDYOL.md 4.2.6).
     */
    private const int MAX_ITEMS = 1000;

    /**
     * Ceiling on a single product's sellable quantity (TRENDYOL.md 4.2.6).
     */
    private const int MAX_QUANTITY = 20000;

    public function __construct(
        private readonly TrendyolClient $client,
        private readonly Repository $config,
        private readonly BrandMapper $brandMapper = new BrandMapper,
        private readonly CategoryMapper $categoryMapper = new CategoryMapper,
        private readonly AttributeMapper $attributeMapper = new AttributeMapper,
        private readonly OrderMapper $orderMapper = new OrderMapper,
    ) {}

    public function identifier(): string
    {
        return 'trendyol';
    }

    public function displayName(): string
    {
        return 'Trendyol';
    }

    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return Capability::supportedBy($this);
    }

    /**
     * Kimlik semasi TRENDYOL.md §2.2/§2.3'ten gelir: sayisal satici id, bir
     * anahtar cifti ve User-Agent'in ikinci yarisi olan entegrator adi. Kademe
     * listesi config'ten okunur, kodda sabit degildir (§7).
     *
     * @return list<array{name: string, label: string, type: 'text'|'secret'|'select'|'checkbox', rules: list<string>, help?: string, options?: list<string>, default?: string, identity?: bool}>
     */
    public function credentialFields(): array
    {
        $tiers = $this->listingTiers();

        return [
            [
                'name' => 'seller_id',
                'label' => 'Satıcı ID',
                'type' => 'text',
                'identity' => true,
                'rules' => ['required', 'string', 'max:20', 'regex:/^[0-9]+$/'],
                'help' => 'Satıcı Paneli → Hesap Bilgilerim → Entegrasyon Bilgileri ekranında "supplierId" olarak görünür.',
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'secret',
                'rules' => ['string', 'max:255'],
            ],
            [
                'name' => 'api_secret',
                'label' => 'API Secret Key',
                'type' => 'secret',
                'rules' => ['string', 'max:255'],
            ],
            [
                'name' => 'integrator',
                'label' => 'Entegratör',
                'type' => 'text',
                'default' => 'SelfIntegration',
                'rules' => ['required', 'string', 'regex:/^[A-Za-z0-9]{1,30}$/'],
                'help' => 'Her isteğe User-Agent olarak gider; eksik veya hatalıysa Trendyol 403 döner. Kendi hesabınızla çalışıyorsanız değiştirmeyin.',
            ],
            [
                'name' => 'listing_tier',
                'label' => 'Ürün limiti kademesi',
                'type' => 'select',
                'options' => $tiers,
                'default' => $tiers[0] ?? '',
                'rules' => ['required', 'string', 'in:'.implode(',', $tiers)],
                'help' => 'Trendyol’un mağazanıza tanıdığı dakikalık istek limiti bu kademeye bağlıdır.',
            ],
            [
                'name' => 'stage',
                'label' => 'Test (stage) ortamı',
                'type' => 'checkbox',
                'rules' => ['boolean'],
                'help' => 'Stage kimlik bilgileri canlıdan farklıdır ve sunucu IP adresinizin Trendyol tarafından izin listesine alınması gerekir.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function listingTiers(): array
    {
        $tiers = $this->config->get('marketplaces.trendyol.rate_limits.groups.product_read');

        return is_array($tiers) ? array_map(strval(...), array_keys($tiers)) : [];
    }

    /**
     * A driver bound to one connection's credentials. The manager resolves this
     * class once per marketplace; credentials are per channel_connection.
     */
    /**
     * Motor hicbir yerde pazaryeri adi gecirmez; kimlik bilgisini bu arayuz
     * uzerinden baglar (BindsCredentials).
     *
     * @param  array<string, mixed>  $credentials
     */
    public function withCredentials(array $credentials): MarketplaceDriver
    {
        return $this->for(TrendyolCredentials::fromArray($credentials));
    }

    public function for(TrendyolCredentials $credentials): self
    {
        return new self(
            $this->client->as($credentials),
            $this->config,
            $this->brandMapper,
            $this->categoryMapper,
            $this->attributeMapper,
            $this->orderMapper,
        );
    }

    /**
     * The truth source for orders: `GET /order/.../orders/stream`.
     *
     * Page number based paging is not a design choice we get to make. The v2
     * paged service caps the reachable result set at 10.000 shipment packages
     * (safe range page 0-49 at size 200), only reaches one month back, and
     * counts split packages individually - so a seller with any volume cannot
     * walk it at all. The stream has no 10.000 window, reaches three months,
     * and hands back an opaque cursor (TRENDYOL.md 4.4.1, 4.4.2, 10.8).
     *
     * Two rules shape this method:
     *
     *  1. `nextCursor` is opaque. It is never parsed, never rebuilt, and once a
     *     stream is running the filters must not change or Trendyol answers 400
     *     with a cursor/filter mismatch. So a continuation request carries the
     *     cursor and nothing else - not even the dates that opened the stream.
     *  2. Ordering is fixed to `lastModifiedDate` DESC and cannot be
     *     configured, which means the newest record arrives on the first page.
     *     The watermark returned here is this page's maximum; only the caller
     *     knows whether the stream drained, so only the caller may commit it.
     *
     * No status filter is sent. The stream filters on `packageItemStatuses`,
     * which is a comma separated *line* level list, not the single package
     * level `status` the paged service takes - different objects, different
     * value semantics, so the two are never routed through one argument
     * (TRENDYOL.md 4.4.2, 5.1 vs 5.3). Pulling everything and mapping status
     * locally also keeps the cursor's filters constant for the life of a
     * stream, which Trendyol requires.
     *
     * @return PullPage<OrderData>
     */
    public function pullOrders(?DateTimeImmutable $updatedSince = null, ?string $cursor = null): PullPage
    {
        $size = min($this->integer('order_stream_page_size', 200), 200);

        $query = $cursor === null || $cursor === ''
            ? ['size' => $size] + $this->streamWindow($updatedSince)
            : ['size' => $size, 'nextCursor' => $cursor];

        $payload = $this->client->get(
            'getShipmentPackagesStream',
            "order/sellers/{$this->sellerId()}/orders/stream",
            $query,
        );

        $items = $this->map($payload['content'] ?? [], $this->orderMapper->toCanonical(...));
        $nextCursor = $payload['nextCursor'] ?? null;

        return new PullPage(
            items: $items,
            hasMore: (bool) ($payload['hasMore'] ?? false),
            cursor: is_string($nextCursor) && $nextCursor !== '' ? $nextCursor : null,
            watermark: $this->highWaterMark($payload['content'] ?? []),
        );
    }

    /**
     * A targeted lookup through the paged v2 service, which is what it is good
     * for: `orderNumber` and `shipmentPackageIds` queries, never scanning.
     *
     * A split or partially cancelled order has several packages under one
     * order number and each is its own canonical order; this returns the first
     * one Trendyol lists. Reconciling the full set is the stream's job.
     */
    public function pullOrder(string $remoteOrderId): ?OrderData
    {
        $payload = $this->client->get(
            'getShipmentPackages',
            "order/sellers/{$this->sellerId()}/v2/orders",
            ['orderNumber' => $remoteOrderId, 'page' => 0, 'size' => 200],
        );

        $items = $this->map($payload['content'] ?? [], $this->orderMapper->toCanonical(...));

        return $items[0] ?? null;
    }

    /**
     * `POST /integration/inventory/.../products/price-and-inventory`.
     *
     * Only the quantity goes out. Trendyol takes the three fields
     * independently and its own guidance is to send only what changed: a
     * request that always carries all three maximises collisions with the 15
     * minute suppression window (TRENDYOL.md 4.2.6, 9.5).
     *
     * @param  list<StockData>  $stock
     */
    public function pushStock(array $stock, MappingContext $context): PushResult
    {
        return $this->priceAndInventory(array_map(
            fn (StockData $item): array => [
                'barcode' => $item->barcode ?? $item->reference,
                // Absolute sellable quantity, never a delta, capped at 20.000.
                'quantity' => max(0, min($item->quantity, self::MAX_QUANTITY)),
            ],
            $stock,
        ));
    }

    /**
     * The same endpoint as the stock push, carrying the other two fields.
     *
     * `listPrice >= salePrice` is validated by Trendyol per item
     * (INVALID_PRICE_RELATION), so a violation is reported through the batch
     * result like any other item failure rather than guessed at here.
     *
     * @param  list<PriceData>  $prices
     */
    public function pushPrices(array $prices, MappingContext $context): PushResult
    {
        return $this->priceAndInventory(array_map(
            static fn (PriceData $item): array => [
                'barcode' => $item->barcode ?? $item->reference,
                'salePrice' => (float) $item->salePrice,
                'listPrice' => (float) $item->listPrice,
            ],
            $prices,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function priceAndInventory(array $items): PushResult
    {
        $items = array_values(array_filter(
            $items,
            static fn (array $item): bool => is_string($item['barcode'] ?? null) && $item['barcode'] !== '',
        ));

        if ($items === []) {
            return PushResult::accepted();
        }

        if (count($items) > self::MAX_ITEMS) {
            // The caller chunks long before this; a batch over the documented
            // ceiling would be accepted and silently truncated, which is the
            // one failure mode the ledger could never see.
            throw new MarketplaceException(
                'Trendyol price and inventory batches take at most '.self::MAX_ITEMS.' items, got '.count($items).'.'
            );
        }

        $payload = $this->client->post(
            'updatePriceAndInventory',
            // The service segment is `inventory`, not `product`, while the
            // result is read from `product` - the most common path mistake in
            // this integration (TRENDYOL.md 4.2.6).
            "inventory/sellers/{$this->sellerId()}/products/price-and-inventory",
            ['items' => $items],
        );

        $batchId = $payload['batchRequestId'] ?? null;

        return is_string($batchId) && $batchId !== ''
            ? PushResult::accepted($batchId)
            : PushResult::rejected('Trendyol batchRequestId dondurmedi.');
    }

    /**
     * `GET /integration/product/.../products/batch-requests/{id}` - the item
     * level truth behind an accepted batch.
     *
     * Two documented traps decide the shape of this method (TRENDYOL.md 6.4):
     * a `ProductInventoryUpdate` batch never returns a top level `status`, so
     * completion is read from the presence of `items[].status`; and
     * `COMPLETED` with `failedItemCount > 0` is a normal partial success, so
     * every item is judged on its own. An empty result set means "still
     * running" and the poller will come back.
     */
    public function batchResult(string $remoteBatchId): PushResult
    {
        $payload = $this->client->get(
            'getBatchRequestResult',
            "product/sellers/{$this->sellerId()}/products/batch-requests/{$remoteBatchId}",
        );

        $rows = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $results = [];

        foreach ($rows as $row) {
            $item = is_array($row) ? $row : [];
            $requestItem = is_array($item['requestItem'] ?? null) ? $item['requestItem'] : [];
            $status = $item['status'] ?? null;
            // The echo is Trendyol's normalisation of what we sent, so its
            // barcode is the canonical one (TRENDYOL.md 6.3, 9.2).
            $barcode = $requestItem['barcode'] ?? null;

            if (! is_string($status) || $status === '' || ! is_string($barcode) || $barcode === '') {
                // One item without a verdict means the batch is still running;
                // reporting the rest now would bury the remainder as "no result".
                return PushResult::accepted($remoteBatchId);
            }

            $reasons = array_values(array_filter(
                is_array($item['failureReasons'] ?? null) ? $item['failureReasons'] : [],
                is_string(...),
            ));

            $results[$barcode] = [
                'accepted' => $status === 'SUCCESS',
                'code' => $status === 'SUCCESS' ? null : $status,
                'message' => $reasons === [] ? null : implode(' · ', $reasons),
            ];
        }

        return PushResult::accepted($remoteBatchId)->withItemResults($results);
    }

    /**
     * Reconciliation's ground truth for stock: the approved product stock and
     * price filter, which returns no content, images or attributes and is
     * therefore the cheap endpoint for a frequent loop (TRENDYOL.md 4.3.4).
     *
     * @return PullPage<StockData>
     */
    public function pullStock(?string $cursor = null): PullPage
    {
        return $this->inventoryAndPricePage($cursor, static fn (array $variant, string $barcode): StockData => new StockData(
            reference: $barcode,
            quantity: (int) ($variant['quantity'] ?? 0),
            sku: isset($variant['stockCode']) ? (string) $variant['stockCode'] : null,
            barcode: $barcode,
        ));
    }

    /**
     * `priceSeenByCustomer` is deliberately ignored: it carries Trendyol's own
     * campaign discount, not the price the seller set, and writing it back
     * would ratchet prices down on every reconciliation (TRENDYOL.md 9.5).
     *
     * @return PullPage<PriceData>
     */
    public function pullPrices(?string $cursor = null): PullPage
    {
        return $this->inventoryAndPricePage($cursor, static fn (array $variant, string $barcode): PriceData => new PriceData(
            reference: $barcode,
            listPrice: number_format((float) ($variant['listPrice'] ?? 0), 2, '.', ''),
            salePrice: number_format((float) ($variant['salePrice'] ?? 0), 2, '.', ''),
            sku: isset($variant['stockCode']) ? (string) $variant['stockCode'] : null,
            barcode: $barcode,
        ));
    }

    /**
     * @template TItem of object
     *
     * @param  callable(array<string, mixed>, string): TItem  $map
     * @return PullPage<TItem>
     */
    private function inventoryAndPricePage(?string $cursor, callable $map): PullPage
    {
        $page = max(0, (int) ($cursor ?? '0'));
        // `page * size <= 10.000` and `size <= 100` on this endpoint.
        $size = min($this->integer('inventory_page_size', 100), 100);

        $payload = $this->client->get(
            'filterApprovedProductsInventoryAndPrice',
            "product/sellers/{$this->sellerId()}/products/approved/inventory-and-price",
            // Ascending keeps a full scan deterministic while rows are written.
            ['page' => $page, 'size' => $size, 'orderByDirection' => 'asc'],
        );

        $items = [];

        foreach (is_array($payload['content'] ?? null) ? $payload['content'] : [] as $product) {
            $variants = is_array($product) && is_array($product['variants'] ?? null) ? $product['variants'] : [];

            foreach ($variants as $variant) {
                $barcode = is_array($variant) ? ($variant['barcode'] ?? null) : null;

                if (is_string($barcode) && $barcode !== '') {
                    /** @var array<string, mixed> $variant */
                    $items[] = $map($variant, $barcode);
                }
            }
        }

        return new PullPage(
            items: $items,
            hasMore: $page + 1 < (int) ($payload['totalPages'] ?? 0),
            cursor: (string) ($page + 1),
        );
    }

    /**
     * The date window that opens a stream, in epoch milliseconds.
     *
     * Sending nothing is legal and makes Trendyol default to the last two
     * weeks, which is the right behaviour for a first ever run. With a
     * watermark we rewind by a deliberate overlap: whether the bounds are
     * inclusive or exclusive is undocumented, so windows are overlapped and
     * duplicates are absorbed by the upsert on shipmentPackageId
     * (TRENDYOL.md 4.4.2).
     *
     * @return array{lastModifiedStartDate?: int, lastModifiedEndDate?: int}
     */
    private function streamWindow(?DateTimeImmutable $updatedSince): array
    {
        if ($updatedSince === null) {
            return [];
        }

        $now = now()->toDateTimeImmutable();
        $overlapMinutes = max(0, $this->integer('order_window_overlap_minutes', 5));
        $earliest = $now->modify('-3 months +1 day');

        $start = $updatedSince->modify("-{$overlapMinutes} minutes");
        $start = $start < $earliest ? $earliest : $start;

        // Hard maximum of two weeks per request; a colder watermark than that
        // is walked forward one window per run.
        $end = $start->modify('+2 weeks');
        $end = $end > $now ? $now : $end;

        return [
            'lastModifiedStartDate' => $start->getTimestamp() * 1000,
            'lastModifiedEndDate' => $end->getTimestamp() * 1000,
        ];
    }

    /**
     * `lastModifiedDate` is in the guide's example response but missing from the
     * OpenAPI ShipmentPackage schema (TRENDYOL.md 4.4.1, contradiction). It is
     * what incremental sync hangs on, so it is read defensively: a page that
     * carries none yields no watermark and the caller keeps the old one rather
     * than skipping records.
     */
    private function highWaterMark(mixed $content): ?DateTimeImmutable
    {
        $highest = 0;

        foreach (is_array($content) ? $content : [] as $package) {
            $value = is_array($package) ? ($package['lastModifiedDate'] ?? null) : null;

            if (is_numeric($value)) {
                $highest = max($highest, (int) $value);
            }
        }

        return $highest > 0
            ? (new DateTimeImmutable('@'.intdiv($highest, 1000)))->setTimezone(new DateTimeZone('UTC'))
            : null;
    }

    /**
     * Every order path is `/order/sellers/{sellerId}/...`, so an unbound client
     * fails here rather than building a URL with an empty segment.
     */
    private function sellerId(): string
    {
        $sellerId = $this->client->credentials()?->sellerId;

        return $sellerId ?? throw new MarketplaceException(
            'No Trendyol credentials bound; call TrendyolDriver::for() with the connection credentials first.'
        );
    }

    /**
     * getBrands returns no pagination metadata whatsoever, so paging stops when
     * a page comes back empty. Whether the page index is 0 or 1 based is
     * undocumented (TRENDYOL.md 4.1.1, Ek A #19) - we start at 0. The catalog is
     * large enough (100k+) that this is a nightly full sync, not a lookup.
     *
     * @return PullPage<BrandData>
     */
    public function brands(?string $cursor = null): PullPage
    {
        $page = max(0, (int) ($cursor ?? '0'));
        $size = $this->integer('brand_page_size', 1000);

        $payload = $this->cached("brands:{$page}:{$size}", 'brands', fn (): array => $this->client->get(
            'getBrands',
            'product/brands',
            ['page' => $page, 'size' => $size],
        ));

        $items = $this->map($payload['brands'] ?? [], $this->brandMapper->toCanonical(...));

        return new PullPage(
            items: $items,
            hasMore: $items !== [],
            cursor: (string) ($page + 1),
        );
    }

    /**
     * The search is case sensitive and its semantics (exact match or contains?)
     * are undocumented (TRENDYOL.md 4.1.2), so only an exact hit is returned:
     * publishing against a near miss gets the product rejected, and lowercasing
     * the name locally would break on Turkish dotted/dotless I anyway.
     *
     * The response is a bare array here, unlike getBrands.
     */
    public function findBrandByName(string $name): ?BrandData
    {
        $rows = $this->cached('brand-by-name:'.md5($name), 'brands', fn (): array => $this->client->get(
            'getBrandsByName',
            'product/brands/by-name',
            ['name' => $name],
        ));

        foreach ($rows as $row) {
            if (is_array($row) && ($row['name'] ?? null) === $name) {
                return $this->brandMapper->toCanonical($row);
            }
        }

        return null;
    }

    /**
     * @return list<CategoryNodeData>
     */
    public function categoryTree(): array
    {
        $rows = $this->cached('categories:'.$this->storefrontCode(), 'categories', fn (): array => $this->client->get(
            'getCategoryTree',
            'product/product-categories',
            headers: $this->catalogHeaders(),
        ));

        return $this->map($rows, $this->categoryMapper->toCanonical(...));
    }

    /**
     * @return list<AttributeData>
     */
    public function categoryAttributes(string $remoteCategoryId): array
    {
        $payload = $this->cached(
            "category-attributes:{$this->storefrontCode()}:{$remoteCategoryId}",
            'attributes',
            fn (): array => $this->client->get(
                'getCategoryAttributes',
                "product/categories/{$remoteCategoryId}/attributes",
                headers: $this->catalogHeaders(),
            ),
        );

        $entries = $payload['categoryAttributes'] ?? [];
        $attributes = [];

        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $attribute = is_array($entry['attribute'] ?? null) ? $entry['attribute'] : [];
            $attributeId = (string) ($attribute['id'] ?? '');

            // ponytail: values are pulled only when Trendyol refuses free text,
            // because that is the only case where the id list is mandatory
            // (TRENDYOL.md 4.1.5). Widen this to every attribute if a mapper
            // ever needs the suggestions of an allowCustom one - it costs one
            // extra request per attribute against a 50/min budget.
            $entry['attributeValues'] = $attributeId === '' || (bool) ($entry['allowCustom'] ?? false)
                ? []
                : $this->attributeValues($remoteCategoryId, $attributeId);

            $attributes[] = $this->attributeMapper->toCanonical($entry);
        }

        return $attributes;
    }

    /**
     * The only reference endpoint with a real pagination envelope.
     *
     * @return list<array<string, mixed>>
     */
    private function attributeValues(string $categoryId, string $attributeId): array
    {
        $cached = $this->cached(
            "category-attribute-values:{$this->storefrontCode()}:{$categoryId}:{$attributeId}",
            'attributes',
            function () use ($categoryId, $attributeId): array {
                $rows = [];
                $page = 0;
                $size = $this->integer('attribute_value_page_size', 1000);

                do {
                    $payload = $this->client->get(
                        'getCategoryAttributeValues',
                        "product/categories/{$categoryId}/attributes/{$attributeId}/values",
                        ['page' => $page, 'size' => $size],
                        $this->catalogHeaders(),
                    );

                    $content = $payload['content'] ?? [];
                    $content = is_array($content) ? $content : [];

                    foreach ($content as $row) {
                        if (is_array($row)) {
                            $rows[] = $row;
                        }
                    }

                    $totalPages = (int) ($payload['totalPages'] ?? 0);
                    $page++;
                } while ($content !== [] && $page < $totalPages);

                return $rows;
            },
        );

        return array_values(array_filter($cached, is_array(...)));
    }

    /**
     * Stale while revalidate with the TTLs of BACKEND-PLAN 7.6: brands a day,
     * categories and attributes a week (Trendyol asks for a weekly refresh).
     *
     * @param  callable(): array<array-key, mixed>  $callback
     * @return array<array-key, mixed>
     */
    private function cached(string $key, string $dataset, callable $callback): array
    {
        return Cache::flexible("trendyol:{$key}", $this->ttl($dataset), $callback);
    }

    /**
     * @return array{0: int, 1: int} fresh and stale seconds
     */
    private function ttl(string $dataset): array
    {
        $ttl = $this->config->get("marketplaces.trendyol.cache.{$dataset}");

        if (is_array($ttl) && is_numeric($ttl[0] ?? null) && is_numeric($ttl[1] ?? null)) {
            return [(int) $ttl[0], (int) $ttl[1]];
        }

        return [86400, 172800];
    }

    /**
     * getCategoryTree, getCategoryAttributes and getCategoryAttributeValues
     * document this header all lowercase while createBrand spells it
     * `storeFrontCode`; send each page's spelling verbatim (TRENDYOL.md 2.5).
     *
     * @return array<string, string>
     */
    private function catalogHeaders(): array
    {
        return [
            'storefrontcode' => $this->storefrontCode(),
            'Accept-Language' => $this->string('accept_language', 'tr'),
        ];
    }

    /**
     * @template TItem of object
     *
     * @param  callable(array<string, mixed>): TItem  $map
     * @return list<TItem>
     */
    private function map(mixed $rows, callable $map): array
    {
        $items = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row)) {
                $items[] = $map($row);
            }
        }

        return $items;
    }

    private function storefrontCode(): string
    {
        return $this->string('storefront_code', 'TR');
    }

    private function integer(string $key, int $default): int
    {
        $value = $this->config->get("marketplaces.trendyol.{$key}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    private function string(string $key, string $default): string
    {
        $value = $this->config->get("marketplaces.trendyol.{$key}", $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
