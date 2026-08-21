<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada\Mappers;

use App\Marketplaces\Data\AttributeValueData;
use App\Marketplaces\Data\MappingContext;
use App\Marketplaces\Data\MatchProposalData;
use App\Marketplaces\Data\ProductData;
use App\Marketplaces\Data\PushResult;
use App\Marketplaces\Data\VariantData;
use App\Marketplaces\Hepsiburada\Enums\HepsiburadaProductStatus;
use App\Marketplaces\Hepsiburada\MerchantSku;

/**
 * The `attributes` map IS the product (HEPSIBURADA.md §9.4).
 *
 * There is no flat product schema on this marketplace: title, description,
 * brand, barcode, VAT, desi, warranty, images and every variant axis are keys
 * of one untyped string map whose key set is decided by the CATEGORY and read
 * at runtime from `getAllAttributesByCategory`. This class is the layer that
 * folds typed canonical columns into that map and unfolds them back.
 *
 * It deliberately does NOT implement Mapper: one ProductData becomes N remote
 * rows (one per variant, joined by `VaryantGroupID`), which the interface's
 * `toRemote(): array<string, mixed>` cannot express.
 *
 * Two rules this class exists to enforce:
 *
 *  - RESERVED keys. A tenant attribute literally named `price`, `stock` or
 *    `merchantSku` would otherwise overwrite a commercial field. Anything whose
 *    resolved remote id is reserved is dropped instead.
 *  - `price` and `stock` are never emitted. The catalog import accepts them and
 *    quietly opens a listing with them the moment the product is accepted
 *    (H9 leak); the listing service is the single owner of commercial fields.
 */
final class ProductMapper
{
    /**
     * `baseAttributes` keys this mapper owns, measured on category 26012174.
     *
     * `GarantiSuresi` is deliberately absent: it is mandatory, has no canonical
     * column at all (§9.11) and therefore has to arrive through
     * `ProductData.attributes[]` or `field_overrides`.
     *
     * @var list<string>
     */
    public const array RESERVED = [
        'merchantSku', 'VaryantGroupID', 'UrunAdi', 'UrunAciklamasi', 'Barcode',
        'Marka', 'tax_vat_rate', 'kg', 'price', 'stock', 'Video1',
        'Image1', 'Image2', 'Image3', 'Image4', 'Image5',
        'Image6', 'Image7', 'Image8', 'Image9', 'Image10',
    ];

    public function __construct(
        /**
         * The documentation says `Image1..Image5`; the measured category
         * exposes `Image1..Image10` (§9.9, Ek A #7 - whether that is universal
         * or per category was not measured). The default is the documented
         * floor, because truncating loses images while overshooting is refused.
         * Silent truncation is data loss: local pre-validation is what warns
         * the seller before the push (BACKEND-PLAN §7.5).
         */
        private readonly int $maxImages = 5,
        /**
         * `kg` is labelled "Desi" - volumetric cargo units, not kilograms
         * (§9.10, measured). Writing a weight straight into it mis-prices
         * shipping on every light-but-bulky product.
         *
         * ⚠️ Hepsiburada's own divisor (3000 or 5000?) is undocumented, Ek A #23.
         */
        private readonly int $desiDivisor = 3000,
    ) {}

    /**
     * One product becomes one row per variant, all carrying the same
     * `VaryantGroupID`.
     *
     * ORDER IS LOAD BEARING. `itemOrderID` in the poll result is the index into
     * the array that goes out (H5), so rows are appended in variant order and
     * the caller preserves the concatenation order across products.
     *
     * @return list<array<string, mixed>>
     */
    public function toRemoteRows(ProductData $product, MappingContext $context, string $merchantId): array
    {
        $categoryId = (int) $context->remoteCategoryId((string) $product->categoryId);
        // §10.3 invariant 3 (H13): the group id is DERIVED from the product
        // reference, never random and never empty. A shared or blank value
        // merges unrelated products into one variant group on the storefront,
        // and even a product with no variants needs a unique one.
        $variantGroupId = MerchantSku::normalise($product->reference);
        $images = array_slice($product->images, 0, $this->maxImages);
        $rows = [];

        foreach ($this->variants($product) as $variant) {
            $attributes = [
                // §10.3 invariants 1 and 2: the normalised sku is the canonical
                // reference AND the value sent as merchantSku. Hepsiburada
                // uppercases it server side, so sending the already normalised
                // form is what keeps the poll result correlatable.
                'merchantSku' => MerchantSku::normalise($variant->sku),
                'VaryantGroupID' => $variantGroupId,
                'UrunAdi' => $product->name,
                'UrunAciklamasi' => $product->description ?? $product->name,
                // `kg` is mandatory, so it is always emitted.
                'kg' => $this->desi($variant),
            ];

            if ($variant->barcode !== null && $variant->barcode !== '') {
                // Identity critical, not metadata: Hepsiburada matches your
                // product into its catalog on this value (§4.1.4).
                $attributes['Barcode'] = $variant->barcode;
            }

            if ($product->brandId !== null) {
                // There is no brand entity on this marketplace: `Marka` is free
                // text, so channel_brand_mappings holds the NAME (§9.7, M7).
                $attributes['Marka'] = $context->remoteBrandId($product->brandId);
            }

            if ($variant->vatRate !== null) {
                $attributes['tax_vat_rate'] = $this->plainNumber($variant->vatRate);
            }

            foreach ($images as $index => $image) {
                $attributes['Image'.($index + 1)] = $image;
            }

            $warranty = $context->override('GarantiSuresi');

            if (is_numeric($warranty)) {
                $attributes['GarantiSuresi'] = (int) $warranty;
            }

            foreach ([...$product->attributes, ...$variant->attributes] as $attribute) {
                [$key, $value] = $this->attribute($attribute, $context);

                if ($key !== null) {
                    $attributes[$key] = $value;
                }
            }

            $rows[] = [
                'categoryId' => $categoryId,
                // The merchantId is simultaneously the Basic username, a path
                // segment and this body field (§9.1).
                'merchant' => $merchantId,
                'attributes' => $attributes,
            ];
        }

        return $rows;
    }

    /**
     * One row of `all-products-of-merchant` (§4.1.9, measured).
     *
     * The measured row is FLAT - `baseAttributes` is a `[{name, value,
     * mandatory}]` array, there is no `fields` map, no revision history and no
     * `listingStatus`, contrary to every secondary source.
     *
     * @param  array<string, mixed>  $remote
     */
    public function toCanonical(array $remote): ProductData
    {
        $base = $this->namedValues($remote['baseAttributes'] ?? []);
        $reference = MerchantSku::normalise((string) ($remote['merchantSku'] ?? ''));
        $hbSku = $this->text($remote['hbSku'] ?? null);

        return new ProductData(
            reference: $reference,
            name: (string) ($remote['productName'] ?? $base['UrunAdi'] ?? ''),
            // Measured: `description` comes back as an empty string - the
            // catalog row carries no commercial content (H9).
            description: $this->text($remote['description'] ?? $base['UrunAciklamasi'] ?? null),
            categoryId: $this->text($remote['categoryId'] ?? null),
            // The brand is a name, which is exactly what a HB brand mapping is.
            brandId: $this->text($remote['brand'] ?? $base['Marka'] ?? null),
            status: HepsiburadaProductStatus::tryFromRemote(
                $this->text($remote['status'] ?? $remote['productStatus'] ?? null)
            )?->toCanonical(),
            variants: [new VariantData(
                reference: $reference,
                sku: $reference,
                barcode: $this->text($remote['barcode'] ?? $base['Barcode'] ?? null),
                attributes: $this->attributeValues($remote['variantTypeAttributes'] ?? []),
                // `tax` reads back with a DOT while the write side wants a
                // comma - the decimal separator is asymmetric (§9.3).
                vatRate: $this->text($remote['tax'] ?? $base['tax_vat_rate'] ?? null),
                // Null until the product is accepted: for the whole pre-match
                // window there is no hbSku to hang a foreign key on (§9.1).
                remoteId: $hbSku,
            )],
            attributes: $this->attributeValues($remote['productAttributes'] ?? []),
            images: $this->images($remote['images'] ?? []),
            remoteId: $hbSku,
        );
    }

    /**
     * A product with no variants still has to produce exactly one row: a
     * blank `VaryantGroupID` would merge it with everything else that sent one.
     *
     * @return list<VariantData>
     */
    private function variants(ProductData $product): array
    {
        return $product->variants === []
            ? [new VariantData(reference: $product->reference, sku: $product->reference)]
            : $product->variants;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function attribute(AttributeValueData $attribute, MappingContext $context): array
    {
        $code = $attribute->attributeCode;

        if ($code === null || $code === '') {
            return [null, null];
        }

        // Unmapped attributes pass through under their canonical code (§9.4):
        // Hepsiburada attribute ids are opaque per-category strings and a
        // missing mapping must not block an otherwise valid product.
        $remoteId = $context->attributeIds[$code] ?? $code;

        if (in_array($remoteId, self::RESERVED, true)) {
            return [null, null];
        }

        // The value is what goes back, never the value id (§4.1.3).
        // ⚠️ How a multiValue attribute encodes several values is undocumented;
        // repeating a key here is last-wins.
        return [$remoteId, $attribute->value];
    }

    /**
     * Desi, rounded up to a whole unit: cargo is billed in whole desi and an
     * integer string sidesteps the comma/dot separator ambiguity entirely.
     */
    private function desi(VariantData $variant): string
    {
        $dimensions = $variant->dimensions;
        $volumetric = ($dimensions['length'] ?? 0) * ($dimensions['width'] ?? 0) * ($dimensions['height'] ?? 0);
        $volumetric = $this->desiDivisor > 0 ? $volumetric / $this->desiDivisor : 0.0;

        return (string) max(1, (int) ceil(max($variant->weight ?? 0.0, $volumetric)));
    }

    /**
     * `tax_vat_rate` and `GarantiSuresi` are whole numbers in every documented
     * example; a canonical "20.00" would read as a malformed value.
     */
    private function plainNumber(string $value): string
    {
        if (! is_numeric($value)) {
            return $value;
        }

        $formatted = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /**
     * @return array<string, string>
     */
    private function namedValues(mixed $rows): array
    {
        $values = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && is_scalar($row['name'] ?? null) && is_scalar($row['value'] ?? null)) {
                $values[(string) $row['name']] = (string) $row['value'];
            }
        }

        return $values;
    }

    /**
     * @return list<AttributeValueData>
     */
    private function attributeValues(mixed $rows): array
    {
        $values = [];

        foreach ($this->namedValues($rows) as $name => $value) {
            $values[] = new AttributeValueData(value: $value, attributeCode: $name);
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private function images(mixed $rows): array
    {
        $images = [];

        foreach (is_array($rows) ? $rows : [] as $image) {
            $url = $this->text($image);

            if ($url !== null) {
                $images[] = $url;
            }
        }

        return $images;
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    /**
     * Poll yanitini kanonik `PushResult`e cevirir.
     *
     * UC DIK STATU EKSENI vardir (§6): `importStatus` DOSYANIN, `productStatus`
     * URUNUN, `importMessages[].severity` SATIRIN durumudur. Bir urun
     * `importStatus=SUCCESS` iken bile bozuk olabilir — bu yuzden karar asla
     * zarftan verilmez, her satir kendi sonucundan kapanir.
     *
     * `itemOrderID` gonderilen dizideki KONUMSAL indekstir; `merchantSku`
     * dondugunde onu tercih ediyoruz, cunku HB onu buyuk harfe cevirse bile
     * bizim `reference` degerimizle ayni normalizasyondan gecer (§10.3).
     *
     * @param  array<array-key, mixed>  $payload
     */
    public function batchResult(array $payload, string $trackingId): PushResult
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $rows = is_array($data['items'] ?? null) ? $data['items'] : $data;

        $items = [];
        $settled = false;

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $reference = $this->text($row['merchantSku'] ?? null);
            $reference = $reference !== null
                ? MerchantSku::normalise($reference)
                : (string) ($row['itemOrderID'] ?? $index);

            $status = HepsiburadaProductStatus::tryFrom((string) ($row['productStatus'] ?? ''));
            $messages = is_array($row['importMessages'] ?? null) ? $row['importMessages'] : [];

            $errors = [];
            $warnings = [];

            foreach ($messages as $message) {
                if (! is_array($message)) {
                    continue;
                }

                $severity = strtoupper((string) ($message['severity'] ?? ''));
                $text = $this->text($message['message'] ?? null) ?? '';

                if ($severity === 'ERROR') {
                    $errors[] = $text;
                } elseif ($severity === 'WARNING') {
                    $warnings[] = $text;
                }
            }

            if ($status !== null) {
                $settled = true;
            }

            $accepted = $errors === [] && $status !== HepsiburadaProductStatus::Rejected;

            $items[$reference] = [
                'accepted' => $accepted,
                // KABUL EDILDI AMA ETKISIZ: fiyat bandi ihlalinde urun
                // reddedilmez, 0 fiyat/0 stok ile canliya cikar ve geriye
                // yalnizca bir UYARI kalir (§3, olculdu). `accepted:false`
                // sonsuz retry uretirdi, `accepted:true` saticiya yalan olurdu.
                'degraded' => $accepted && $warnings !== [],
                'code' => $status?->value,
                'message' => implode(' | ', $errors !== [] ? $errors : $warnings) ?: null,
            ];
        }

        // Hicbir satir henuz bir verdict tasimıyorsa is HALA CALISIYOR demektir;
        // bos item kumesi dondurup poller'in yeniden denemesini sagliyoruz.
        return $settled
            ? PushResult::accepted($trackingId)->withItemResults($items)
            : PushResult::accepted($trackingId);
    }

    /**
     * `PRE_MATCHED` bir urun satirini, saticinin karar vermesi icin gereken
     * karsi oneriye cevirir. Urun, karar verilene kadar SATISA ACILMAZ.
     *
     * @param  array<string, mixed>  $remote
     */
    public function toMatchProposal(array $remote): MatchProposalData
    {
        $matched = is_array($remote['matchedHbProductInfo'] ?? null) ? $remote['matchedHbProductInfo'] : [];

        return new MatchProposalData(
            reference: MerchantSku::normalise((string) ($remote['merchantSku'] ?? '')),
            proposedRemoteId: (string) ($matched['hbSku'] ?? $remote['hbSku'] ?? ''),
            proposedName: $this->text($matched['productName'] ?? $remote['productName'] ?? null),
            proposedBrand: $this->text($matched['brand'] ?? $remote['brand'] ?? null),
            proposedImages: $this->images($matched['images'] ?? $remote['images'] ?? null),
            proposedAttributes: $this->attributeValues($matched['baseAttributes'] ?? $remote['baseAttributes'] ?? null),
            proposedCategoryName: $this->text($matched['categoryName'] ?? $remote['categoryName'] ?? null),
        );
    }
}
