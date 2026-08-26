<?php

declare(strict_types=1);

namespace App\Actions\Sync;

use App\Enums\AllocationType;
use App\Enums\MarkupType;
use App\Enums\RuleScope;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\PriceData;
use App\Marketplaces\Data\StockData;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ChannelPriceRule;
use App\Models\ChannelStockRule;
use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Turns "this variant changed" into outbox intents, one per listed channel.
 *
 * This is the heart of "one inventory, N channels" (BACKEND-PLAN 7.7). The
 * single source of truth is `inventory_items.available`; what each marketplace
 * is told is a *derived* number: the pool minus the channel's buffer, shaped by
 * that channel's `channel_stock_rules` row. Prices work the same way through
 * `channel_price_rules`. Nothing here talks to a marketplace - it records the
 * state it wants and returns (BACKEND-PLAN 7.2).
 *
 * Only variants that already carry a `channel_listings` row are pushed. A
 * product that was never listed is a `product_sync` job and that capability is
 * blocked on the P0 attribute contradiction of TRENDYOL.md Ek A #1.
 *
 * The debounce that keeps forty changes in ten seconds down to one request
 * lives on the drain job, so this action never delays anything itself.
 */
final class QueueVariantSync
{
    public function __construct(private readonly EnqueueOperation $enqueue) {}

    /**
     * Both sides of a listing, for a variant that has just become listed.
     */
    public function all(ProductVariant $variant, ?ChannelConnection $only = null): void
    {
        $this->stock($variant, $only);
        $this->price($variant, $only);
    }

    public function stock(ProductVariant $variant, ?ChannelConnection $only = null): void
    {
        $barcode = $this->barcode($variant);
        $connections = $barcode === null ? null : $this->connections($variant, $only);

        if ($barcode === null || $connections === null || $connections->isEmpty()) {
            return;
        }

        $pool = $this->pool($variant);
        $variant->loadMissing('product');

        /** @var EloquentCollection<int, ChannelStockRule> $rules */
        $rules = ChannelStockRule::query()->whereIn('connection_id', $connections->modelKeys())->get();

        foreach ($connections as $connection) {
            $rule = $this->stockRule($rules, $connection, $variant);

            ($this->enqueue)(
                $connection,
                OperationType::StockUpdate,
                ProductVariant::class,
                (int) $variant->getKey(),
                new StockData(
                    reference: $barcode,
                    quantity: $this->allocate($pool, $rule),
                    sku: $variant->sku,
                    barcode: $barcode,
                ),
            );
        }
    }

    public function price(ProductVariant $variant, ?ChannelConnection $only = null): void
    {
        $barcode = $this->barcode($variant);
        $price = $barcode === null ? null : $this->currentPrice($variant);
        $connections = $price === null ? null : $this->connections($variant, $only);

        if ($barcode === null || $price === null || $connections === null || $connections->isEmpty()) {
            return;
        }

        $variant->loadMissing('product');

        /** @var EloquentCollection<int, ChannelPriceRule> $rules */
        $rules = ChannelPriceRule::query()->whereIn('connection_id', $connections->modelKeys())->get();

        $listPrice = $this->minorUnits($price->list_price);
        $salePrice = $this->minorUnits($price->sale_price ?? $price->list_price);

        foreach ($connections as $connection) {
            $rule = $this->priceRule($rules, $connection, $variant);
            $sale = $this->markup($salePrice, $rule);

            ($this->enqueue)(
                $connection,
                OperationType::PriceUpdate,
                ProductVariant::class,
                (int) $variant->getKey(),
                new PriceData(
                    reference: $barcode,
                    // `listPrice >= salePrice` is a hard marketplace rule
                    // (TRENDYOL.md 9.5, INVALID_PRICE_RELATION). Our own markup
                    // must never be the thing that violates it.
                    listPrice: $this->amount(max($this->markup($listPrice, $rule), $sale)),
                    salePrice: $this->amount($sale),
                    currency: $price->currency,
                    sku: $variant->sku,
                    barcode: $barcode,
                ),
            );
        }
    }

    /**
     * The active connections this variant is actually listed on.
     *
     * @return EloquentCollection<int, ChannelConnection>
     */
    private function connections(ProductVariant $variant, ?ChannelConnection $only): EloquentCollection
    {
        /** @var EloquentCollection<int, ChannelConnection> $connections */
        $connections = ChannelConnection::query()
            ->active()
            ->when($only !== null, fn (Builder $query): Builder => $query->whereKey($only?->getKey()))
            ->whereIn('id', ChannelListing::query()
                ->where('variant_id', $variant->getKey())
                ->select('connection_id'))
            ->get();

        return $connections;
    }

    /**
     * The sellable pool across every warehouse. `available` is a generated
     * column (`on_hand - reserved`); `safety_stock` is the slice that never
     * leaves the shelf, so it comes off per row rather than off the total -
     * one overdrawn warehouse must not eat another's safety margin.
     */
    private function pool(ProductVariant $variant): int
    {
        $pool = InventoryItem::query()
            ->where('variant_id', $variant->getKey())
            ->selectRaw('coalesce(sum(greatest(available - safety_stock, 0)), 0) as pool')
            ->value('pool');

        return max(0, (int) $pool);
    }

    /**
     * No rule means "mirror the pool": one inventory, shown whole on the one
     * channel. Splitting is what a rule is for.
     */
    private function allocate(int $pool, ?ChannelStockRule $rule): int
    {
        if ($rule === null) {
            return $pool;
        }

        // The buffer is stock we simply refuse to promise anywhere, so it comes
        // off before the channel takes its share, whatever the share is.
        $pool = max(0, $pool - $rule->buffer);
        $value = (float) ($rule->allocation_value ?? 0);

        return match ($rule->allocation_type) {
            AllocationType::Percent => (int) floor($pool * $value / 100),
            AllocationType::Fixed => min($pool, max(0, (int) $value)),
            AllocationType::Remaining => $pool,
        };
    }

    /**
     * Markup in minor units: money never touches a float long enough to drift,
     * and the rounding step is applied last so `round_to` decides the price the
     * customer sees rather than the markup arithmetic.
     */
    private function markup(int $minorUnits, ?ChannelPriceRule $rule): int
    {
        if ($rule === null) {
            return $minorUnits;
        }

        $value = (float) $rule->markup_value;

        $marked = match ($rule->markup_type) {
            MarkupType::Percent => (int) round($minorUnits * (100 + $value) / 100),
            MarkupType::Fixed => $minorUnits + (int) round($value * 100),
        };

        $step = $rule->round_to === null ? 0 : (int) round((float) $rule->round_to * 100);

        return max(0, $step > 0 ? (int) round($marked / $step) * $step : $marked);
    }

    /**
     * ponytail: the newest price row valid right now, in whatever currency it
     * carries. Per channel currency selection is not modelled - add it as a
     * connection setting the day a second currency exists.
     */
    private function currentPrice(ProductVariant $variant): ?Price
    {
        return Price::query()
            ->where('variant_id', $variant->getKey())
            ->where(fn (Builder $query): Builder => $query->whereNull('valid_from')->orWhere('valid_from', '<=', now()))
            ->where(fn (Builder $query): Builder => $query->whereNull('valid_to')->orWhere('valid_to', '>=', now()))
            ->orderByRaw('valid_from desc nulls last')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  EloquentCollection<int, ChannelStockRule>  $rules
     */
    private function stockRule(
        EloquentCollection $rules,
        ChannelConnection $connection,
        ProductVariant $variant,
    ): ?ChannelStockRule {
        $best = null;
        $bestRank = PHP_INT_MAX;

        foreach ($rules as $rule) {
            $rank = $rule->connection_id === $connection->getKey()
                ? $this->rank($rule->scope_type, $rule->scope_id, $variant)
                : null;

            if ($rank !== null && $rank < $bestRank) {
                $best = $rule;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    /**
     * @param  EloquentCollection<int, ChannelPriceRule>  $rules
     */
    private function priceRule(
        EloquentCollection $rules,
        ChannelConnection $connection,
        ProductVariant $variant,
    ): ?ChannelPriceRule {
        $best = null;
        $bestRank = PHP_INT_MAX;

        foreach ($rules as $rule) {
            $rank = $rule->connection_id === $connection->getKey()
                ? $this->rank($rule->scope_type, $rule->scope_id, $variant)
                : null;

            if ($rank !== null && $rank < $bestRank) {
                $best = $rule;
                $bestRank = $rank;
            }
        }

        return $best;
    }

    /**
     * How specific a rule scope is for this variant, lower being more specific.
     * Null means the rule does not apply at all.
     *
     * ponytail: the two resolvers above are the same eight lines twice, because
     * a shared generic one cannot keep `ChannelStockRule` and `ChannelPriceRule`
     * apart in its return type - and losing that distinction is worse than the
     * duplication. Merge them the day the two tables get a shared base model.
     */
    private function rank(RuleScope $scope, ?int $scopeId, ProductVariant $variant): ?int
    {
        return match ($scope) {
            RuleScope::Variant => $scopeId === $variant->getKey() ? 0 : null,
            RuleScope::Category => $scopeId !== null && $scopeId === $variant->product->category_id ? 1 : null,
            RuleScope::Brand => $scopeId !== null && $scopeId === $variant->product->brand_id ? 2 : null,
            RuleScope::Connection => 3,
        };
    }

    /**
     * Trendyol strips interior whitespace on the way in and expects stock
     * updates keyed on the barcode it took in (TRENDYOL.md 9.2, K11).
     * Normalising once, here, keeps the ledger reference, the wire barcode and
     * the batch result echo the same string - and no marketplace accepts a
     * barcode with a space in it anyway.
     */
    private function barcode(ProductVariant $variant): ?string
    {
        $barcode = preg_replace('/\s+/u', '', (string) $variant->barcode);

        return is_string($barcode) && $barcode !== '' ? $barcode : null;
    }

    private function minorUnits(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function amount(int $minorUnits): string
    {
        return number_format($minorUnits / 100, 2, '.', '');
    }
}
