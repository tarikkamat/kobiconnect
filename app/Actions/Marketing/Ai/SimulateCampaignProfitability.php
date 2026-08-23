<?php

declare(strict_types=1);

namespace App\Actions\Marketing\Ai;

use App\Ai\Agents\CampaignProfitabilitySimulatorAgent;
use App\Models\Product;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class SimulateCampaignProfitability
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(
        Product $product,
        string $campaignName,
        float $campaignDiscountPercentage,
        float $commissionSubventionPercentage = 0.0
    ): array {
        $agent = new CampaignProfitabilitySimulatorAgent;

        $variant = $product->variants->first();
        $priceModel = $variant?->prices->first();
        $currentPrice = (float) ($priceModel ? ($priceModel->sale_price ?? $priceModel->list_price) : 300.0);
        $costPrice = (float) ($priceModel && $priceModel->cost !== null ? $priceModel->cost : ($currentPrice * 0.45));
        $effectiveCommission = max(0.05, 0.18 - ($commissionSubventionPercentage / 100));

        $prompt = sprintf(
            "Ürün Adı: %s\nMevcut Fiyat: %.2f TL\nÜrün Maliyeti: %.2f TL\nKampanya Adı: %s\nTalep Edilen İndirim Oranı: %%%.1f\nPazaryeri Komisyon Desteği: %%%.1f (Net Komisyon: %%%.1f)\nKargo Maliyeti: 45.00 TL\n\nLütfen bu kampanyaya katılımın kârlılık simülasyonunu yap ve net tavsiye oluştur.",
            $product->name,
            $currentPrice,
            $costPrice,
            $campaignName,
            $campaignDiscountPercentage,
            $commissionSubventionPercentage,
            $effectiveCommission * 100
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return [
            'product_id' => $product->id,
            'campaign_name' => $campaignName,
            'current_price' => $currentPrice,
            'discount_percentage' => $campaignDiscountPercentage,
            'recommendation' => $data['recommendation'] ?? 'participate',
            'projected_net_margin_percentage' => $data['projected_net_margin_percentage'] ?? 15.0,
            'projected_unit_profit' => $data['projected_unit_profit'] ?? 45.0,
            'breakeven_sales_multiplier' => $data['breakeven_sales_multiplier'] ?? 1.2,
            'warning' => $data['warning'] ?? null,
            'counter_strategy' => $data['counter_strategy'] ?? null,
            'simulation_summary' => $data['simulation_summary'] ?? '',
        ];
    }
}
