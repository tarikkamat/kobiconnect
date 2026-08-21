<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada;

use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Contracts\SupportsCatalogMatching;
use App\Marketplaces\Contracts\SupportsCategoryCatalog;
use App\Marketplaces\Contracts\SupportsInventorySync;
use App\Marketplaces\Contracts\SupportsOrderSync;
use App\Marketplaces\Contracts\SupportsPriceSync;
use App\Marketplaces\Contracts\SupportsProductSync;
use App\Marketplaces\Data\AttributeData;
use App\Marketplaces\Data\CategoryNodeData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\OrderData;
use App\Marketplaces\Data\PriceData;
use App\Marketplaces\Data\ProductData;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Data\StockData;
use App\Marketplaces\Hepsiburada\Enums\HepsiburadaProductStatus;
use App\Marketplaces\Hepsiburada\Mappers\AttributeMapper;
use App\Marketplaces\Hepsiburada\Mappers\CategoryMapper;
use App\Marketplaces\Hepsiburada\Mappers\OrderMapper;
use App\Marketplaces\Hepsiburada\Mappers\ProductMapper;
use App\Marketplaces\Support\Capability;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Support\Sync\BindsCredentials;
use App\Support\Sync\ReadsBatchResults;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;

/**
 * Hepsiburada, tek bir API degil: katalog, listing ve siparis UC AYRI HOST'ta
 * yasar ve her birinin yanit zarfi farklidir (HEPSIBURADA.md H1, olculdu).
 * Host secimi `HepsiburadaService` ile yapilir; surucu bunun disinda Trendyol
 * surucusuyle ayni sekle sahiptir.
 *
 * BILEREK IMPLEMENT EDILMEYEN:
 * - `SupportsBrandCatalog` — Hepsiburada'da marka VARLIGI yoktur. `Marka`
 *   serbest metindir, endpoint'i, id'si ve arama ucu yoktur (§9). Yetenegi
 *   iddia etmek arayuze var olmayan bir marka secici koydururdu.
 * - `SupportsShipmentUpdates`, `SupportsClaims` yazma, `SupportsQuestions`,
 *   `SupportsWebhooks` — v1.1 / kapsam disi (§1).
 */
final class HepsiburadaDriver implements BindsCredentials, MarketplaceDriver, ReadsBatchResults, SupportsCatalogMatching, SupportsCategoryCatalog, SupportsInventorySync, SupportsOrderSync, SupportsPriceSync, SupportsProductSync
{
    public function __construct(
        private readonly HepsiburadaClient $client,
        private readonly Repository $config,
        private readonly CategoryMapper $categoryMapper,
        private readonly AttributeMapper $attributeMapper,
        private readonly ProductMapper $productMapper,
        private readonly OrderMapper $orderMapper,
    ) {}

    public function identifier(): string
    {
        return 'hepsiburada';
    }

    public function displayName(): string
    {
        return 'Hepsiburada';
    }

    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return Capability::supportedBy($this);
    }

    /**
     * HEPSIBURADA.md §2.1: OAuth yok, token yok — HTTP Basic'te kullanici adi
     * merchantId UUID'si, sifre saticinin kendi panelinden urettigi "Servis
     * Anahtari". Entegrator adi CIPLAK kullanici adidir; Trendyol'un
     * "{merchantId} - Ad" bicimi burada 401/403 dondurur, o yuzden bosluk
     * kabul edilmez.
     *
     * @return list<array{name: string, label: string, type: 'text'|'secret'|'select'|'checkbox', rules: list<string>, help?: string, options?: list<string>, default?: string, identity?: bool}>
     */
    public function credentialFields(): array
    {
        return [
            [
                'name' => 'merchant_id',
                'label' => 'Merchant ID',
                'type' => 'text',
                'identity' => true,
                'rules' => ['required', 'uuid'],
                'help' => 'Satıcı panelinde "merchantId" olarak görünen UUID.',
            ],
            [
                'name' => 'service_key',
                'label' => 'Servis Anahtarı',
                'type' => 'secret',
                'rules' => ['string', 'max:255'],
                'help' => 'Satıcı panelinden üretilir ve oradan yenilenebilir; 401 aldığınızda anahtarı yeniden alın.',
            ],
            [
                'name' => 'integrator_user_agent',
                'label' => 'Entegratör kullanıcı adı',
                'type' => 'text',
                'rules' => ['required', 'string', 'regex:/^[A-Za-z0-9._-]{1,64}$/'],
                'help' => 'Boşluksuz, çıplak entegratör kullanıcı adı (örn. kobiconnect). Trendyol’daki "{merchantId} - Ad" biçimi Hepsiburada’da reddedilir.',
            ],
            [
                'name' => 'sit',
                'label' => 'Test (SIT) ortamı',
                'type' => 'checkbox',
                'rules' => ['boolean'],
                'help' => 'SIT host’ları ayrı kimlik bilgileri ister; canlı anahtarlar orada çalışmaz.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function withCredentials(array $credentials): MarketplaceDriver
    {
        return $this->for(HepsiburadaCredentials::fromArray($credentials));
    }

    public function for(HepsiburadaCredentials $credentials): self
    {
        return new self(
            $this->client->as($credentials),
            $this->config,
            $this->categoryMapper,
            $this->attributeMapper,
            $this->productMapper,
            $this->orderMapper,
        );
    }

    // ---------------------------------------------------------------- katalog

    /**
     * @return list<CategoryNodeData>
     */
    public function categoryTree(): array
    {
        $nodes = [];
        $page = 0;

        // Ust sinir SART: `last` bayragina kosulsuz guvenmek, bozuk ya da
        // tekrar eden bir yanitta sonsuz donguye ve bellek tukenmesine yol
        // acar. Kategori agaci olculen ortamda ~2800 sayfa (5611 kayit).
        $maxPages = (int) $this->config->get('marketplaces.hepsiburada.max_pages', 5000);

        do {
            $payload = $this->client->get(
                HepsiburadaService::Catalog,
                'getAllCategoriesByParameters',
                '/product/api/categories/get-all-categories',
                ['leaf' => 'true', 'status' => 'ACTIVE', 'page' => $page, 'size' => $this->pageSize()],
            );

            /** @var list<array<string, mixed>> $rows */
            $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            foreach ($rows as $row) {
                $nodes[] = $this->categoryMapper->toCanonical($row);
            }

            $last = (bool) ($payload['last'] ?? true);
            $page++;
        } while (! $last && $rows !== [] && $page < $maxPages);

        return $nodes;
    }

    /**
     * @return list<AttributeData>
     */
    public function categoryAttributes(string $remoteCategoryId): array
    {
        $payload = $this->client->get(
            HepsiburadaService::Catalog,
            'getAllAttributesByCategory',
            "/product/api/categories/{$remoteCategoryId}/attributes",
        );

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        // Hepsiburada attribute'lari UC KOVAYA ayirir: baseAttributes urun
        // zarfinin alanlaridir, attributes kategoriye ozgudur, variantAttributes
        // varyanti belirler. Kanonik model tek liste bekler; varyant bilgisi
        // AttributeMapper tarafindan `isVarianter` bayragina cevrilir (§9).
        $attributes = [];

        foreach (['baseAttributes', 'attributes', 'variantAttributes'] as $bucket) {
            $rows = is_array($data[$bucket] ?? null) ? $data[$bucket] : [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $attribute = $this->attributeMapper->toCanonical(
                    $row + ['__bucket' => $bucket],
                );

                $attributes[] = $attribute->allowsCustomValue
                    ? $attribute
                    : $this->withValues($attribute, $remoteCategoryId);
            }
        }

        return $attributes;
    }

    /**
     * Degerler yalnizca serbest metne izin verilmeyen attribute'lar icin
     * cekilir: kategori x attribute fan-out'u N*M'dir ve butce IP basinadir.
     */
    private function withValues(AttributeData $attribute, string $categoryId): AttributeData
    {
        // ⚠️ `attribute` TEKIL — cogulu 404 doner (olculdu, §4).
        $payload = $this->client->get(
            HepsiburadaService::Catalog,
            'getAllAttributeValuesByCategoryIdAndAttributeId',
            "/product/api/categories/{$categoryId}/attribute/{$attribute->remoteId}/values",
            ['page' => 0, 'size' => $this->pageSize()],
        );

        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return new AttributeData(
            remoteId: $attribute->remoteId,
            name: $attribute->name,
            isRequired: $attribute->isRequired,
            allowsCustomValue: $attribute->allowsCustomValue,
            allowsMultipleValues: $attribute->allowsMultipleValues,
            isVarianter: $attribute->isVarianter,
            isSlicer: $attribute->isSlicer,
            values: $this->attributeMapper->values($rows),
            type: $attribute->type,
        );
    }

    // ------------------------------------------------------------------ urun

    /**
     * @param  list<ProductData>  $products
     */
    public function createProducts(array $products, MappingContext $context): PushResult
    {
        return $this->import($products, $context);
    }

    /**
     * @param  list<ProductData>  $products
     */
    public function updateProducts(array $products, MappingContext $context): PushResult
    {
        // MVP'de guncelleme de `import` uzerinden gider. `ticket-api` yolu
        // (MATCHED sonrasi kismi guncelleme) v1.1 — tam dokuman gondermek
        // HB'nin editoryal zenginlestirmesini ezer (§10 M13).
        return $this->import($products, $context);
    }

    /**
     * @param  list<ProductData>  $products
     */
    private function import(array $products, MappingContext $context): PushResult
    {
        $merchantId = $this->merchantId();
        $rows = [];

        // SIRA YUK TASIR: poll sonucundaki `itemOrderID` tam olarak bu dizinin
        // indeksidir (§10.3). Satirlar hicbir yerde yeniden anahtarlanmaz.
        foreach ($products as $product) {
            foreach ($this->productMapper->toRemoteRows($product, $context, $merchantId) as $row) {
                $rows[] = $row;
            }
        }

        if ($rows === []) {
            return PushResult::accepted();
        }

        $payload = $this->client->upload(
            HepsiburadaService::Catalog,
            'uploadProductViaFile',
            '/product/api/products/import',
            $rows,
        );

        $trackingId = $this->trackingId($payload);

        return $trackingId === null
            ? PushResult::rejected('Hepsiburada bir trackingId dondurmedi.')
            : PushResult::accepted($trackingId);
    }

    public function productPushResult(string $remoteBatchId): PushResult
    {
        return $this->batchResult($remoteBatchId);
    }

    /**
     * HTTP 200 basari DEGILDIR: uc dik statu ekseni vardir ve bir urun
     * `importStatus=SUCCESS` iken bile bozuk olabilir (§6).
     */
    public function batchResult(string $remoteBatchId): PushResult
    {
        $payload = $this->client->get(
            HepsiburadaService::Catalog,
            'getProductStatusByTraceId',
            "/product/api/products/status/{$remoteBatchId}",
        );

        return $this->productMapper->batchResult($payload, $remoteBatchId);
    }

    public function pullProducts(?DateTimeImmutable $updatedSince = null, ?string $cursor = null): PullPage
    {
        $page = $cursor === null ? 0 : (int) $cursor;

        $payload = $this->client->get(
            HepsiburadaService::Catalog,
            'getAllProductsByMerchantId',
            '/product/api/products/all-products-of-merchant/'.$this->merchantId(),
            ['page' => $page, 'size' => $this->pageSize()],
        );

        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $items = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = $this->productMapper->toCanonical($row);
            }
        }

        $last = (bool) ($payload['last'] ?? true);

        return new PullPage(
            items: $items,
            hasMore: ! $last,
            cursor: $last ? null : (string) ($page + 1),
        );
    }

    // ---------------------------------------------------------- on eslesme

    public function pendingMatchProposals(?string $cursor = null): PullPage
    {
        $page = $cursor === null ? 0 : (int) $cursor;

        $payload = $this->client->get(
            HepsiburadaService::Catalog,
            'getProductByMerchantIdAndStatus',
            '/product/api/products/products-by-merchant-and-status',
            [
                'merchantId' => $this->merchantId(),
                'status' => HepsiburadaProductStatus::PreMatched->value,
                'page' => $page,
                'size' => $this->pageSize(),
            ],
        );

        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $items = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $items[] = $this->productMapper->toMatchProposal($row);
            }
        }

        $last = (bool) ($payload['last'] ?? true);

        return new PullPage(
            items: $items,
            hasMore: ! $last,
            cursor: $last ? null : (string) ($page + 1),
        );
    }

    /**
     * @param  list<string>  $references
     */
    public function approveMatches(array $references): PushResult
    {
        return $this->decideMatches('/product/api/products/approve-prematch', 'integratorApprovePreMatch', $references);
    }

    /**
     * @param  list<string>  $references
     */
    public function rejectMatches(array $references): PushResult
    {
        return $this->decideMatches('/product/api/products/reject-prematch', 'integratorRejectPreMatch', $references);
    }

    /**
     * @param  list<string>  $references
     */
    private function decideMatches(string $path, string $endpoint, array $references): PushResult
    {
        if ($references === []) {
            return PushResult::accepted();
        }

        $payload = $this->client->post(
            HepsiburadaService::Catalog,
            $endpoint,
            $path,
            [
                'merchantId' => $this->merchantId(),
                'merchantSkuList' => array_map(MerchantSku::normalise(...), $references),
            ],
        );

        $trackingId = $this->trackingId($payload);

        return $trackingId === null ? PushResult::accepted() : PushResult::accepted($trackingId);
    }

    // ------------------------------------------------------- fiyat ve stok

    /**
     * @param  list<StockData>  $stock
     */
    public function pushStock(array $stock, MappingContext $context): PushResult
    {
        return $this->listingUpload('stock-uploads', 'stock', array_map(
            fn (object $item): array => [
                'merchantSku' => MerchantSku::normalise((string) $item->reference),
                'availableStock' => (int) $item->quantity,
            ],
            $stock,
        ));
    }

    /**
     * @param  list<PriceData>  $prices
     */
    public function pushPrices(array $prices, MappingContext $context): PushResult
    {
        return $this->listingUpload('price-uploads', 'price', array_map(
            fn (object $item): array => [
                'merchantSku' => MerchantSku::normalise((string) $item->reference),
                // ⚠️ Virgullu Turkce ondalik STRING. Nokta ayirici sessiz hatadir
                // ve urun 0 fiyatla canliya cikar (§9).
                'price' => number_format((float) $item->salePrice, 2, ',', ''),
            ],
            $prices,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function listingUpload(string $kind, string $endpoint, array $rows): PushResult
    {
        if ($rows === []) {
            return PushResult::accepted();
        }

        $payload = $this->client->post(
            HepsiburadaService::Listing,
            $endpoint,
            '/listings/merchantid/'.$this->merchantId().'/'.$kind,
            $rows,
        );

        $id = $this->trackingId($payload);

        return $id === null
            ? PushResult::rejected('Hepsiburada bir yukleme kimligi dondurmedi.')
            : PushResult::accepted($id);
    }

    /**
     * @return PullPage<StockData>
     */
    public function pullStock(?string $cursor = null): PullPage
    {
        return $this->listings($cursor, fn (array $row): StockData => new StockData(
            reference: MerchantSku::normalise((string) ($row['merchantSku'] ?? '')),
            quantity: (int) ($row['availableStock'] ?? 0),
            sku: is_string($row['merchantSku'] ?? null) ? $row['merchantSku'] : null,
        ));
    }

    /**
     * @return PullPage<PriceData>
     */
    public function pullPrices(?string $cursor = null): PullPage
    {
        // Ayni listing satiri hem fiyat hem stok tasir; cagirana gore dogru
        // kanonik DTO'ya cevriliyor.
        return $this->listings($cursor, fn (array $row): PriceData => new PriceData(
            reference: MerchantSku::normalise((string) ($row['merchantSku'] ?? '')),
            listPrice: (string) ($row['price'] ?? '0'),
            salePrice: (string) ($row['price'] ?? '0'),
            sku: is_string($row['merchantSku'] ?? null) ? $row['merchantSku'] : null,
        ));
    }

    /**
     * Listing okuma mutabakatin dogruluk kaynagidir: `isSalable` ve
     * `deactivationReasons[]` "kabul edildi ama etkisiz" durumunu OLCULEBILIR
     * kilar (§1).
     *
     * @template TRow of object
     *
     * @param  callable(array<string, mixed>): TRow  $map
     * @return PullPage<TRow>
     */
    private function listings(?string $cursor, callable $map): PullPage
    {
        $offset = $cursor === null ? 0 : (int) $cursor;
        $limit = $this->pageSize();

        $payload = $this->client->get(
            HepsiburadaService::Listing,
            'getListings',
            '/listings/merchantid/'.$this->merchantId(),
            ['offset' => $offset, 'limit' => $limit],
        );

        // Listing zarfi katalogunkinden farklidir: `{listings: [...]}` (olculdu).
        $rows = is_array($payload['listings'] ?? null) ? $payload['listings'] : [];
        $items = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $items[] = $map($row);
        }

        $hasMore = count($rows) === $limit;

        return new PullPage(
            items: $items,
            hasMore: $hasMore,
            cursor: $hasMore ? (string) ($offset + $limit) : null,
        );
    }

    // -------------------------------------------------------------- siparis

    public function pullOrders(?DateTimeImmutable $updatedSince = null, ?string $cursor = null): PullPage
    {
        $offset = $cursor === null ? 0 : (int) $cursor;
        $limit = $this->pageSize();

        $payload = $this->client->get(
            HepsiburadaService::Oms,
            'getOrders',
            '/orders/merchantid/'.$this->merchantId(),
            ['offset' => $offset, 'limit' => $limit],
        );

        // Siparis zarfi ucuncu bir sekildir: `{totalCount, limit, offset,
        // pageCount, items: []}` ve sayfalama page/size degil limit/offset.
        $rows = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $orders = $this->orderMapper->fromLines(array_values(array_filter($rows, is_array(...))));

        $total = (int) ($payload['totalCount'] ?? 0);
        $hasMore = $offset + $limit < $total;

        return new PullPage(
            items: $orders,
            hasMore: $hasMore,
            cursor: $hasMore ? (string) ($offset + $limit) : null,
        );
    }

    public function pullOrder(string $remoteOrderId): ?OrderData
    {
        $payload = $this->client->get(
            HepsiburadaService::Oms,
            'getOrderByNumber',
            '/orders/merchantid/'.$this->merchantId().'/ordernumber/'.$remoteOrderId,
        );

        $rows = is_array($payload['items'] ?? null) ? $payload['items'] : $payload;
        $orders = $this->orderMapper->fromLines(array_values(array_filter((array) $rows, is_array(...))));

        return $orders[0] ?? null;
    }

    // -------------------------------------------------------------- yardimci

    /**
     * Yazma uclari kimligi farkli anahtarlarla dondurur (`trackingId`, `Id`,
     * `id`, `data`), bu yuzden tek yerde normalize ediliyor.
     *
     * @param  array<array-key, mixed>  $payload
     */
    private function trackingId(array $payload): ?string
    {
        foreach (['trackingId', 'Id', 'id', 'data'] as $key) {
            $value = $payload[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function merchantId(): string
    {
        $credentials = $this->client->credentials();

        if ($credentials === null) {
            throw new MarketplaceException('Hepsiburada surucusu kimlik bilgisi olmadan kullanilamaz; once withCredentials() cagirin.');
        }

        return $credentials->merchantId;
    }

    private function pageSize(): int
    {
        // Katalog tarafinda size 100 ile SINIRLI (satici notu + olculdu).
        return (int) $this->config->get('marketplaces.hepsiburada.page_size', 100);
    }
}
