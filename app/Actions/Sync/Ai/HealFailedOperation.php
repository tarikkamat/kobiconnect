<?php

declare(strict_types=1);

namespace App\Actions\Sync\Ai;

use App\Ai\Agents\SelfHealingSyncAgent;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelOperation;
use App\Models\Product;
use App\Models\ProductVariant;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class HealFailedOperation
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(ChannelOperation $operation): array
    {
        $error = $operation->error ?? [];
        $desiredState = $operation->desired_state ?? [];

        // Ürün ve varyant bilgilerini çöz
        $product = null;
        if ($operation->entity_type === 'product') {
            $product = Product::find($operation->entity_id);
        } elseif ($operation->entity_type === 'variant') {
            $variant = ProductVariant::with('product')->find($operation->entity_id);
            $product = $variant?->product;
        }

        $productName = $product instanceof Product ? $product->name : 'Bilinmiyor';
        $productDesc = $product instanceof Product ? ($product->description ?? 'Yok') : 'Yok';
        /** @var array<string, mixed> $productAttrs */
        $productAttrs = (array) ($product instanceof Product ? ($product->getAttribute('attributes') ?? []) : []);

        $agent = new SelfHealingSyncAgent;

        $prompt = sprintf(
            "Pazaryeri Hata Kodu / Mesajı: %s\nHata Detayı: %s\nMevcut Gönderim Yükü (Desired State): %s\nÜrün Adı: %s\nÜrün Açıklaması: %s\nÜrün Nitelikleri: %s\n\nLütfen bu hatayı analiz et, eksik veriyi ürün açıklaması/başlığından çıkar ve gönderim yükünü onar.",
            json_encode($error, JSON_UNESCAPED_UNICODE),
            json_encode($operation->remote_result ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($desiredState, JSON_UNESCAPED_UNICODE),
            $productName,
            $productDesc,
            json_encode($productAttrs, JSON_UNESCAPED_UNICODE)
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        if (! empty($data['repaired'])) {
            $repairedPayload = is_array($data['repaired_payload']) ? $data['repaired_payload'] : $desiredState;
            $mergedState = array_merge($desiredState, $repairedPayload);

            // Eksik nitelik varsa ürüne de kaydet
            if ($product instanceof Product && ! empty($data['missing_attribute_name']) && ! empty($data['extracted_value'])) {
                /** @var array<string, mixed> $attrs */
                $attrs = (array) ($product->getAttribute('attributes') ?? []);
                $attrs[(string) $data['missing_attribute_name']] = $data['extracted_value'];
                $product->update(['attributes' => $attrs]);
            }

            // Operasyonu onar ve tekrar sıraya al
            $operation->update([
                'desired_state' => $mergedState,
                'status' => SyncState::Pending,
                'error' => null,
                'remote_result' => [
                    'self_healed' => true,
                    'healed_at' => now()->toIso8601String(),
                    'summary' => $data['repair_summary'] ?? 'Otomatik onarıldı.',
                ],
            ]);

            return [
                'success' => true,
                'operation_id' => $operation->id,
                'repaired' => true,
                'repair_summary' => $data['repair_summary'] ?? 'Otomatik onarıldı.',
                'extracted_attribute' => [
                    'name' => $data['missing_attribute_name'] ?? null,
                    'value' => $data['extracted_value'] ?? null,
                ],
                'new_status' => SyncState::Pending->value,
            ];
        }

        return [
            'success' => false,
            'operation_id' => $operation->id,
            'repaired' => false,
            'root_cause' => $data['root_cause'] ?? 'Onarılamadı',
            'repair_summary' => $data['repair_summary'] ?? 'Eksik veri ürün detaylarından çıkarılamadı.',
        ];
    }
}
