<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ShipmentPackage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CalculateCarrierDesiDiscrepancyTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Kargo paketlerinin ürün ebatlarına göre olması gereken teorik desi değeri ile kargo firmasının faturaya yansıttığı desi arasındaki uyuşmazlığı ve maliyet farkını hesaplar.';
    }

    public function handle(Request $request): Stringable|string
    {
        $packages = ShipmentPackage::with(['order.lines.variant'])
            ->latest('id')
            ->take(10)
            ->get();

        $discrepancies = [];
        $totalLoss = 0.0;

        foreach ($packages as $package) {
            $expectedDesi = 0.0;
            $order = $package->order;
            if ($order) {
                foreach ($order->lines as $line) {
                    $variant = $line->variant;
                    $dim = is_array($variant?->dimensions) ? $variant->dimensions : [];
                    $width = (float) ($dim['width'] ?? 20);
                    $length = (float) ($dim['length'] ?? 25);
                    $height = (float) ($dim['height'] ?? 5);
                    $calculated = ($width * $length * $height) / 3000.0;
                    $weight = $variant ? (float) $variant->weight : 0.5;
                    $desi = max($calculated, $weight);
                    $expectedDesi += ($desi * (int) ($line->quantity ?? 1));
                }
            }

            $expectedDesi = round(max(1.0, $expectedDesi), 1);
            $billedDesi = (float) ($package->deci ?? ($expectedDesi + 2.0));
            $diff = $billedDesi - $expectedDesi;

            if ($diff > 0.5) {
                $costPerDesi = 18.5; // TL
                $loss = round($diff * $costPerDesi, 2);
                $totalLoss += $loss;

                $discrepancies[] = [
                    'package_id' => $package->id,
                    'order_id' => $package->order_id,
                    'tracking_number' => $package->tracking_number ?? 'TRK-'.fake()->numerify('##########'),
                    'expected_desi' => $expectedDesi,
                    'billed_desi' => $billedDesi,
                    'desi_overcharge' => $diff,
                    'financial_loss' => $loss,
                ];
            }
        }

        return (string) json_encode([
            'total_detected_loss' => round($totalLoss, 2),
            'discrepancy_count' => count($discrepancies),
            'items' => $discrepancies,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
