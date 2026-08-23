<?php

declare(strict_types=1);

namespace App\Actions\Logistics\Ai;

use App\Ai\Agents\ReconciliationAuditorAgent;
use App\Models\ShipmentPackage;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class AuditCarrierDesiOvercharges
{
    /**
     * @param  Collection<int, ShipmentPackage>|array<int, ShipmentPackage>|null  $packages
     * @return array<string, mixed>
     */
    public function __invoke(Collection|array|null $packages = null): array
    {
        $packagesList = $packages ?? ShipmentPackage::with(['order.lines.variant'])->latest('id')->take(20)->get();

        $auditItems = [];
        $costPerDesi = 18.5; // TL standart desi aşım birim maliyeti

        foreach ($packagesList as $pkg) {
            $expectedDesi = 0.0;
            $order = $pkg->order;
            if ($order) {
                foreach ($order->lines as $line) {
                    $variant = $line->variant;
                    $dim = is_array($variant?->dimensions) ? $variant->dimensions : [];
                    $w = (float) ($dim['width'] ?? 20);
                    $l = (float) ($dim['length'] ?? 25);
                    $h = (float) ($dim['height'] ?? 5);
                    $calc = ($w * $l * $h) / 3000.0;
                    $wt = $variant ? (float) $variant->weight : 0.5;
                    $expectedDesi += (max($calc, $wt) * (int) ($line->quantity ?? 1));
                }
            }

            $expectedDesi = round(max(1.0, $expectedDesi), 1);
            $billedDesi = (float) ($pkg->deci ?? ($expectedDesi + 2.0));
            $diff = $billedDesi - $expectedDesi;

            if ($diff > 0.3) {
                $loss = round($diff * $costPerDesi, 2);
                $auditItems[] = [
                    'package_id' => $pkg->id,
                    'order_id' => (string) $pkg->order_id,
                    'tracking_number' => $pkg->tracking_number ?? 'TRK-'.fake()->numerify('##########'),
                    'expected_desi' => $expectedDesi,
                    'billed_desi' => $billedDesi,
                    'financial_loss' => $loss,
                ];
            }
        }

        $agent = new ReconciliationAuditorAgent;

        $prompt = sprintf(
            "Aşağıdaki kargo gönderilerinde kargo firması tarafından desi aşımı (beklenenden yüksek desi faturalandırması) tespit edilmiştir:\n\n%s\n\nLütfen toplam maddi kaybı hesapla ve kargo firması genel müdürlüğüne iletilmek üzere resmi tahkim ve itiraz dilekçesi metni oluştur.",
            json_encode($auditItems, JSON_UNESCAPED_UNICODE)
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return [
            'total_detected_loss' => $data['total_detected_loss'] ?? 0.0,
            'currency' => $data['currency'] ?? 'TRY',
            'discrepancies' => $data['discrepancies'] ?? [],
            'dispute_summary' => $data['dispute_summary'] ?? '',
            'formal_dispute_letter' => $data['formal_dispute_letter'] ?? '',
            'scanned_packages_count' => count($packagesList),
        ];
    }
}
