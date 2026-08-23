<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SimulateCampaignImpactTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Pazaryeri indirim kampanyası için uygulanan indirim oranı, maliyet ve komisyon girdilerine göre kârlılık simülasyonu yapar.';
    }

    public function handle(Request $request): Stringable|string
    {
        $currentPrice = (float) ($request['current_price'] ?? 300.0);
        $discountPercentage = (float) ($request['discount_percentage'] ?? 15.0);
        $costPrice = (float) ($request['cost_price'] ?? ($currentPrice * 0.45));
        $commissionRate = (float) ($request['commission_rate'] ?? 0.18);
        $shippingCost = (float) ($request['shipping_cost'] ?? 45.0);

        $discountedPrice = $currentPrice * (1 - ($discountPercentage / 100));
        $commissionFee = $discountedPrice * $commissionRate;
        $netProfit = $discountedPrice - $costPrice - $commissionFee - $shippingCost;
        $netMargin = $discountedPrice > 0 ? round(($netProfit / $discountedPrice) * 100, 2) : 0;

        $currentCommissionFee = $currentPrice * $commissionRate;
        $currentNetProfit = $currentPrice - $costPrice - $currentCommissionFee - $shippingCost;
        $currentNetMargin = $currentPrice > 0 ? round(($currentNetProfit / $currentPrice) * 100, 2) : 0;

        $breakevenUnits = $netProfit > 0 ? round($currentNetProfit / $netProfit, 2) : 999.0;

        return (string) json_encode([
            'current_price' => $currentPrice,
            'discounted_price' => round($discountedPrice, 2),
            'current_net_margin_percentage' => $currentNetMargin,
            'projected_net_margin_percentage' => $netMargin,
            'current_unit_profit' => round($currentNetProfit, 2),
            'projected_unit_profit' => round($netProfit, 2),
            'breakeven_sales_multiplier' => $breakevenUnits,
            'is_healthy' => $netMargin >= 10.0 && $netProfit > 0,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'current_price' => $schema->number()->required()->description('Mevcut satış fiyatı (TL)'),
            'discount_percentage' => $schema->number()->required()->description('Kampanya indirim yüzdesi (%)'),
            'cost_price' => $schema->number()->description('Ürün alış maliyeti (TL)'),
            'commission_rate' => $schema->number()->description('Komisyon oranı (örn: 0.18 = %18)'),
        ];
    }
}
