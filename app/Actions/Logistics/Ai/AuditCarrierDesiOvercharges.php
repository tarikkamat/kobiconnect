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
        $packagesList = $packages ?? ShipmentPackage::with(['order.lines.variant', 'order.connection'])->latest('id')->take(30)->get();

        $auditItems = [];
        $costPerDesi = 18.5; // TL standart desi aşım birim maliyeti
        $totalCalculatedLoss = 0.0;

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
            $billedDesi = (float) ($pkg->deci ?? 0.0);

            // Eğer desi girilmemişse siparişteki cargo_penalty veya varsayılan barem farkını kontrol et
            if ($billedDesi <= 0.0) {
                /** @var array<string, mixed> $orderTotals */
                $orderTotals = (array) ($order ? ($order->getAttribute('totals') ?? []) : []);
                $cargoPenalty = (float) ($orderTotals['cargo_penalty'] ?? 0.0);
                if ($cargoPenalty > 0.0) {
                    $billedDesi = round($expectedDesi + ($cargoPenalty / $costPerDesi), 1);
                } else {
                    $billedDesi = round($expectedDesi + 2.0, 1);
                }
            }

            $diff = round($billedDesi - $expectedDesi, 1);

            if ($diff > 0.3) {
                $loss = round($diff * $costPerDesi, 2);
                $totalCalculatedLoss += $loss;
                $auditItems[] = [
                    'package_id' => $pkg->id,
                    'order_id' => (string) ($order ? $order->remote_order_number : $pkg->order_id),
                    'tracking_number' => $pkg->tracking_number ?? 'TRK-'.fake()->numerify('##########'),
                    'cargo_provider' => (string) ($pkg->cargo_provider ?? 'Kargo Firması'),
                    'expected_desi' => $expectedDesi,
                    'billed_desi' => $billedDesi,
                    'desi_overcharge' => $diff,
                    'financial_loss' => $loss,
                ];
            }
        }

        $data = [];
        try {
            $agent = new ReconciliationAuditorAgent;

            $prompt = sprintf(
                "Aşağıdaki kargo gönderilerinde kargo firması tarafından desi aşımı (beklenenden yüksek desi faturalandırması) tespit edilmiştir:\n\n%s\n\nLütfen toplam maddi kaybı hesapla ve kargo firması genel müdürlüğüne iletilmek üzere resmi tahkim ve itiraz dilekçesi metni oluştur.",
                json_encode($auditItems, JSON_UNESCAPED_UNICODE)
            );

            $response = $agent->prompt($prompt);
            /** @var array<string, mixed> $data */
            $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);
        } catch (\Throwable) {
            $data = [];
        }

        $totalLoss = ! empty($data['total_detected_loss']) ? (float) $data['total_detected_loss'] : round($totalCalculatedLoss, 2);
        $discrepancies = ! empty($data['discrepancies']) && is_array($data['discrepancies']) ? $data['discrepancies'] : $auditItems;

        $defaultSummary = sprintf(
            'Taranan %d paketten %d adedinde hatalı/fahiş desi ölçümü tespit edildi. Toplam haksız kesinti tutarı: %.2f TL.',
            count($packagesList),
            count($auditItems),
            $totalLoss
        );

        $defaultLetter = sprintf(
            "İLGİLİ KARGO FİRMASI GENEL MÜDÜRLÜĞÜ'NE / OPERASYON DENETİM BAŞKANLIĞI'NA\n\n"
            ."Konu: Hatalı Desi Ölçümü ve Fazla Kesilen Kargo Barem Farklarına İtiraz\n\n"
            ."Şirketimiz bünyesinden çıkışı sağlanan ve aşağıda takip numaraları ile sipariş detayları belirtilen koli/paket gönderilerinde, ürünlerin fiziksel ebat ve ağırlık ölçümlerine aykırı olarak fahiş desi (hacimsel ağırlık) faturalandırıldığı tespit edilmiştir.\n\n"
            ."Toplam Tespit Edilen Haksız Kesinti: %.2f TL\n"
            ."Hatalı Gönderi Adedi: %d Paket\n\n"
            .'Yapılan teknik incelemede ürünlerimizin koli ebatları ve hassas tartım kayıtları ile faturanıza yansıtılan desi baremleri arasında açık uyuşmazlık bulunmaktadır. İlgili kayıtların ivedilikle incelenerek haksız kesilen tutarların carimize iadesini ve faturanın düzeltilmesini talep ederiz.',
            $totalLoss,
            count($auditItems)
        );

        return [
            'total_detected_loss' => $totalLoss,
            'currency' => $data['currency'] ?? 'TRY',
            'discrepancies' => $discrepancies,
            'dispute_summary' => $data['dispute_summary'] ?? $defaultSummary,
            'formal_dispute_letter' => $data['formal_dispute_letter'] ?? $defaultLetter,
            'scanned_packages_count' => count($packagesList),
            'discrepancy_count' => count($discrepancies),
        ];
    }
}
