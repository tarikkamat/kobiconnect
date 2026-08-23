<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pricing;

use App\Actions\Inventory\Ai\ForecastStockAndReorders;
use App\Actions\Marketing\Ai\SimulateCampaignProfitability;
use App\Actions\Pricing\Ai\CalculateDynamicPrice;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiPricingCampaignController extends Controller
{
    public function dynamicPrice(Request $request, ProductVariant $variant, CalculateDynamicPrice $action): JsonResponse
    {
        $validated = $request->validate([
            'competitor_price' => ['nullable', 'numeric', 'min:0'],
            'competitor_stock_status' => ['nullable', 'string', 'in:low_stock,out_of_stock,healthy,unknown'],
        ]);

        $variant->loadMissing(['product', 'prices']);
        $result = $action(
            $variant,
            (float) ($validated['competitor_price'] ?? 0.0),
            (string) ($validated['competitor_stock_status'] ?? 'unknown')
        );

        return response()->json([
            'success' => true,
            'pricing' => $result,
        ]);
    }

    public function forecastStock(Request $request, ProductVariant $variant, ForecastStockAndReorders $action): JsonResponse
    {
        $validated = $request->validate([
            'lead_time_days' => ['nullable', 'integer', 'min:1'],
            'upcoming_season' => ['nullable', 'string'],
        ]);

        $variant->loadMissing(['product', 'inventoryItems']);
        $result = $action(
            $variant,
            (int) ($validated['lead_time_days'] ?? 5),
            (string) ($validated['upcoming_season'] ?? 'Standart Dönem')
        );

        return response()->json([
            'success' => true,
            'forecast' => $result,
        ]);
    }

    public function simulateCampaign(Request $request, Product $product, SimulateCampaignProfitability $action): JsonResponse
    {
        $validated = $request->validate([
            'campaign_name' => ['required', 'string'],
            'discount_percentage' => ['required', 'numeric', 'min:1', 'max:99'],
            'commission_subvention_percentage' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ]);

        $product->loadMissing(['variants.prices']);
        $result = $action(
            $product,
            $validated['campaign_name'],
            (float) $validated['discount_percentage'],
            (float) ($validated['commission_subvention_percentage'] ?? 0.0)
        );

        return response()->json([
            'success' => true,
            'simulation' => $result,
        ]);
    }
}
