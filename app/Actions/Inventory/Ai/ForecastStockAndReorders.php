<?php

declare(strict_types=1);

namespace App\Actions\Inventory\Ai;

use App\Ai\Agents\PredictiveStockPlannerAgent;
use App\Models\Product;
use App\Models\ProductVariant;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class ForecastStockAndReorders
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ProductVariant $variant, int $supplierLeadTimeDays = 5, string $upcomingSeason = 'Standart Dönem'): array
    {
        $agent = new PredictiveStockPlannerAgent;

        $onHand = (int) $variant->inventoryItems()->sum('on_hand');
        $reserved = (int) $variant->inventoryItems()->sum('reserved');
        $availableStock = max(0, $onHand - $reserved);

        $dailyVelocity = 4.5; // Günlük ortalama satış adedi
        $productName = $variant->product instanceof Product ? $variant->product->name : 'Ürün';

        $prompt = sprintf(
            "Ürün: %s (SKU: %s)\nMevcut Kullanılabilir Depo Stoğu: %d adet\nGünlük Ortalama Satış Hızı: %.1f adet/gün\nTedarikçi Teslimat Süresi (Lead Time): %d gün\nYaklaşan Dönem / Sezon: %s\n\nLütfen stok tükenme tarihini ve verilmesi gereken sipariş miktarını hesapla.",
            $productName,
            $variant->sku,
            $availableStock,
            $dailyVelocity,
            $supplierLeadTimeDays,
            $upcomingSeason
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return [
            'variant_id' => $variant->id,
            'available_stock' => $availableStock,
            'supplier_lead_time_days' => $supplierLeadTimeDays,
            'days_until_stockout' => $data['days_until_stockout'] ?? 10,
            'predicted_stockout_date' => $data['predicted_stockout_date'] ?? now()->addDays(10)->toDateString(),
            'recommended_reorder_date' => $data['recommended_reorder_date'] ?? now()->addDays(5)->toDateString(),
            'recommended_reorder_quantity' => $data['recommended_reorder_quantity'] ?? 100,
            'urgency' => $data['urgency'] ?? 'healthy',
            'sales_velocity_daily' => $data['sales_velocity_daily'] ?? $dailyVelocity,
            'seasonal_impact_factor' => $data['seasonal_impact_factor'] ?? 1.0,
            'action_plan' => $data['action_plan'] ?? '',
        ];
    }
}
