<?php

namespace App\Marketplaces\Support;

use App\Concerns\HasLabels;
use App\Marketplaces\Contracts\MarketplaceDriver;
use App\Marketplaces\Contracts\SupportsBrandCatalog;
use App\Marketplaces\Contracts\SupportsCatalogMatching;
use App\Marketplaces\Contracts\SupportsCategoryCatalog;
use App\Marketplaces\Contracts\SupportsClaims;
use App\Marketplaces\Contracts\SupportsInventorySync;
use App\Marketplaces\Contracts\SupportsOrderSync;
use App\Marketplaces\Contracts\SupportsPriceSync;
use App\Marketplaces\Contracts\SupportsProductSync;
use App\Marketplaces\Contracts\SupportsQuestions;
use App\Marketplaces\Contracts\SupportsShipmentUpdates;
use App\Marketplaces\Contracts\SupportsWebhooks;
use App\Marketplaces\Support\Exceptions\UnsupportedCapabilityException;

/**
 * The single decision point for "does this marketplace do X".
 *
 * Capabilities are derived from the interfaces a driver implements, so the UI
 * and the scheduler cannot drift from what the driver actually supports.
 */
enum Capability: string
{
    use HasLabels;

    case ProductSync = 'product_sync';

    case InventorySync = 'inventory_sync';

    case PriceSync = 'price_sync';

    case OrderSync = 'order_sync';

    case ShipmentUpdates = 'shipment_updates';

    case Claims = 'claims';

    case Questions = 'questions';

    case CategoryCatalog = 'category_catalog';

    case BrandCatalog = 'brand_catalog';

    /** Pazaryeri katalog eslesmesi onerir, satici onayi bekler. */
    case CatalogMatching = 'catalog_matching';

    case Webhooks = 'webhooks';

    /**
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     */
    public function label(): string
    {
        return match ($this) {
            self::ProductSync => 'Ürün',
            self::InventorySync => 'Stok',
            self::PriceSync => 'Fiyat',
            self::OrderSync => 'Sipariş',
            self::ShipmentUpdates => 'Kargo',
            self::Claims => 'İade',
            self::Questions => 'Soru-Cevap',
            self::CatalogMatching => 'Ürün eşleştirme',
            self::CategoryCatalog => 'Kategori kataloğu',
            self::BrandCatalog => 'Marka kataloğu',
            self::Webhooks => 'Webhook',
        };
    }

    /**
     * The contract a driver implements to declare this capability.
     *
     * @return class-string
     */
    public function contract(): string
    {
        return match ($this) {
            self::ProductSync => SupportsProductSync::class,
            self::InventorySync => SupportsInventorySync::class,
            self::PriceSync => SupportsPriceSync::class,
            self::OrderSync => SupportsOrderSync::class,
            self::ShipmentUpdates => SupportsShipmentUpdates::class,
            self::Claims => SupportsClaims::class,
            self::Questions => SupportsQuestions::class,
            self::CategoryCatalog => SupportsCategoryCatalog::class,
            self::BrandCatalog => SupportsBrandCatalog::class,
            self::CatalogMatching => SupportsCatalogMatching::class,
            self::Webhooks => SupportsWebhooks::class,
        };
    }

    public function driverSupports(MarketplaceDriver $driver): bool
    {
        return $driver instanceof ($this->contract());
    }

    /**
     * @throws UnsupportedCapabilityException
     */
    public function ensureSupported(MarketplaceDriver $driver): void
    {
        if (! $this->driverSupports($driver)) {
            throw UnsupportedCapabilityException::for($this, $driver);
        }
    }

    /**
     * Every capability the given driver implements.
     *
     * @return list<self>
     */
    public static function supportedBy(MarketplaceDriver $driver): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $capability): bool => $capability->driverSupports($driver),
        ));
    }
}
