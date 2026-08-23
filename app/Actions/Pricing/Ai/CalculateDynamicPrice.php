<?php

declare(strict_types=1);

namespace App\Actions\Pricing\Ai;

use App\Ai\Agents\DynamicPricingAgent;
use App\Models\Product;
use App\Models\ProductVariant;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class CalculateDynamicPrice
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ProductVariant $variant, float $competitorPrice = 0.0, string $competitorStockStatus = 'unknown'): array
    {
        $agent = new DynamicPricingAgent;

        $priceModel = $variant->prices->first();
        $listPrice = (float) ($priceModel ? ($priceModel->sale_price ?? $priceModel->list_price) : 250.0);
        $costPrice = (float) ($priceModel && $priceModel->cost !== null ? $priceModel->cost : ($listPrice * 0.45));
        $commissionRate = 0.18; // %18
        $shippingCost = 45.0; // TL
        $minMarginTarget = 0.15; // %15 minimum net kâr marjı tabanı

        // Taban fiyat formülü: (Maliyet + Kargo) / (1 - Komisyon - HedefMarj)
        $marginFloorPrice = round(($costPrice + $shippingCost) / (1 - $commissionRate - $minMarginTarget), 2);

        $productName = $variant->product instanceof Product ? $variant->product->name : 'Ürün';

        $prompt = sprintf(
            "Ürün: %s (SKU: %s)\nMevcut Satış Fiyatımız: %.2f TL\nAlış Maliyetimiz: %.2f TL\nKomisyon Oranı: %%18\nKargo Maliyeti: %.2f TL\nMinimum Kâr Taban Fiyatımız (Margin Floor): %.2f TL\nRakip En Düşük Fiyatı: %.2f TL\nRakip Stok Durumu: %s\n\nLütfen marjımızı koruyarak maksimum kârlı Buybox satış fiyatını belirle.",
            $productName,
            $variant->sku,
            $listPrice,
            $costPrice,
            $shippingCost,
            $marginFloorPrice,
            $competitorPrice,
            $competitorStockStatus
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return [
            'variant_id' => $variant->id,
            'current_price' => $listPrice,
            'competitor_price' => $competitorPrice,
            'margin_floor_price' => $data['margin_floor_price'] ?? $marginFloorPrice,
            'recommended_price' => $data['recommended_price'] ?? $listPrice,
            'action' => $data['action'] ?? 'hold_price',
            'projected_margin_percentage' => $data['projected_margin_percentage'] ?? 20.0,
            'competitor_stock_status' => $data['competitor_stock_status'] ?? $competitorStockStatus,
            'pricing_rationale' => $data['pricing_rationale'] ?? '',
        ];
    }
}
