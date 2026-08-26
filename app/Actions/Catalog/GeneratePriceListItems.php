<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Enums\AdjustmentType;
use App\Enums\PriceListType;
use App\Enums\PriceRuleField;
use App\Enums\RoundingMethod;
use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\PriceListRule;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class GeneratePriceListItems
{
    /**
     * Fiyat listesi kalemlerini hesaplar veya günceller.
     *
     * @return int Güncellenen / oluşturulan kalem sayısı
     */
    public function execute(PriceList $priceList): int
    {
        $priceList->load('rules');

        $variants = ProductVariant::query()
            ->with([
                'product.tags',
                'prices',
            ])
            ->get();

        if ($variants->isEmpty()) {
            return 0;
        }

        $items = [];
        $now = now();

        match ($priceList->type) {
            PriceListType::Currency => $this->generateCurrencyItems($priceList, $variants, $items, $now),
            PriceListType::Dynamic => $this->generateDynamicItems($priceList, $variants, $items, $now),
            PriceListType::Manual => $this->generateManualItems($priceList, $variants, $items, $now),
        };

        if (! empty($items)) {
            DB::transaction(function () use ($items): void {
                PriceListItem::upsert(
                    $items,
                    ['price_list_id', 'variant_id'],
                    ['list_price', 'sale_price', 'currency', 'updated_at']
                );
            });
        }

        return count($items);
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @param  list<array<string, mixed>>  $items
     */
    private function generateCurrencyItems(PriceList $priceList, Collection $variants, array &$items, \DateTimeInterface|string $now): void
    {
        $rate = (float) ($priceList->exchange_rate ?? 1.0);
        if ($rate <= 0) {
            $rate = 1.0;
        }

        foreach ($variants as $variant) {
            $basePrice = $variant->prices->firstWhere('currency', $priceList->source_currency)
                ?? $variant->prices->firstWhere('currency', 'TRY')
                ?? $variant->prices->first();

            if ($basePrice === null) {
                continue;
            }

            $rawList = (float) $basePrice->list_price * $rate;
            $rawSale = $basePrice->sale_price !== null ? (float) $basePrice->sale_price * $rate : null;

            $items[] = [
                'price_list_id' => $priceList->getKey(),
                'variant_id' => $variant->getKey(),
                'list_price' => $this->round($rawList, $priceList->rounding_method),
                'sale_price' => $rawSale !== null ? $this->round($rawSale, $priceList->rounding_method) : null,
                'currency' => $priceList->target_currency,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @param  list<array<string, mixed>>  $items
     */
    private function generateDynamicItems(PriceList $priceList, Collection $variants, array &$items, \DateTimeInterface|string $now): void
    {
        $rules = $priceList->rules;

        foreach ($variants as $variant) {
            $basePrice = $variant->prices->firstWhere('currency', $priceList->source_currency)
                ?? $variant->prices->firstWhere('currency', 'TRY')
                ?? $variant->prices->first();

            if ($basePrice === null) {
                continue;
            }

            $matchingRule = $this->findMatchingRule($variant, $rules);

            $rawList = (float) $basePrice->list_price;
            $rawSale = $basePrice->sale_price !== null ? (float) $basePrice->sale_price : null;

            if ($matchingRule !== null) {
                $rawList = $this->applyAdjustment($rawList, $matchingRule);
                if ($rawSale !== null) {
                    $rawSale = $this->applyAdjustment($rawSale, $matchingRule);
                }
            }

            $items[] = [
                'price_list_id' => $priceList->getKey(),
                'variant_id' => $variant->getKey(),
                'list_price' => $this->round($rawList, $priceList->rounding_method),
                'sale_price' => $rawSale !== null ? $this->round($rawSale, $priceList->rounding_method) : null,
                'currency' => $priceList->target_currency,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @param  list<array<string, mixed>>  $items
     */
    private function generateManualItems(PriceList $priceList, Collection $variants, array &$items, \DateTimeInterface|string $now): void
    {
        $existingItemVariantIds = PriceListItem::query()
            ->where('price_list_id', $priceList->getKey())
            ->pluck('variant_id')
            ->flip()
            ->all();

        foreach ($variants as $variant) {
            // Manuel listede zaten var olan fiyatları ezme, sadece eksik olanları ekle
            if (isset($existingItemVariantIds[$variant->getKey()])) {
                continue;
            }

            $basePrice = $variant->prices->firstWhere('currency', $priceList->target_currency)
                ?? $variant->prices->firstWhere('currency', 'TRY')
                ?? $variant->prices->first();

            $listPrice = $basePrice !== null ? (float) $basePrice->list_price : 0.00;
            $salePrice = $basePrice?->sale_price !== null ? (float) $basePrice->sale_price : null;

            $items[] = [
                'price_list_id' => $priceList->getKey(),
                'variant_id' => $variant->getKey(),
                'list_price' => $listPrice,
                'sale_price' => $salePrice,
                'currency' => $priceList->target_currency,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
    }

    /**
     * @param  Collection<int, PriceListRule>  $rules
     */
    private function findMatchingRule(ProductVariant $variant, $rules): ?PriceListRule
    {
        $product = $variant->product;

        foreach ($rules as $rule) {
            $matched = match ($rule->field) {
                PriceRuleField::All => true,
                PriceRuleField::Category => $product !== null && (int) $product->category_id === (int) $rule->condition_value,
                PriceRuleField::Brand => $product !== null && (int) $product->brand_id === (int) $rule->condition_value,
                PriceRuleField::Product => $product !== null && (int) $product->id === (int) $rule->condition_value,
                PriceRuleField::Tag => $product !== null && $product->tags->contains('id', (int) $rule->condition_value),
            };

            if ($matched) {
                return $rule;
            }
        }

        return null;
    }

    private function applyAdjustment(float $amount, PriceListRule $rule): float
    {
        $val = (float) $rule->adjustment_value;

        return match ($rule->adjustment_type) {
            AdjustmentType::Percentage => $amount * (1 + ($val / 100)),
            AdjustmentType::Fixed => max(0, $amount + $val),
        };
    }

    private function round(float $amount, RoundingMethod $method): float
    {
        return match ($method) {
            RoundingMethod::Round => round($amount, 2),
            RoundingMethod::Ceil => ceil($amount * 100) / 100,
            RoundingMethod::Floor => floor($amount * 100) / 100,
            RoundingMethod::None => round($amount, 2),
        };
    }
}
