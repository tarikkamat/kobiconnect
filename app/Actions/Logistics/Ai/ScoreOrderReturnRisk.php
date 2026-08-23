<?php

declare(strict_types=1);

namespace App\Actions\Logistics\Ai;

use App\Ai\Agents\ReturnRiskScorerAgent;
use App\Models\Order;
use App\Models\OrderLine;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class ScoreOrderReturnRisk
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Order $order): array
    {
        $agent = new ReturnRiskScorerAgent;

        $linesData = $order->lines->map(fn (OrderLine $l): array => [
            'sku' => $l->sku,
            'barcode' => $l->barcode,
            'quantity' => $l->quantity,
            'unit_price' => $l->unit_price,
            'attributes' => $l->variant?->attributes,
        ])->all();

        $customerName = 'Müşteri';
        if ($order->customer) {
            $customerName = (string) $order->customer;
        }

        $prompt = sprintf(
            "Sipariş No: %s\nMüşteri: %s\nToplam Tutar: %s\nÜrünler: %s\nTeslimat Bilgisi: %s",
            $order->remote_order_number ?? (string) $order->id,
            $customerName,
            json_encode($order->totals ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($linesData, JSON_UNESCAPED_UNICODE),
            json_encode($order->raw ?? [], JSON_UNESCAPED_UNICODE)
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return [
            'order_id' => $order->id,
            'risk_level' => $data['risk_level'] ?? 'low',
            'risk_score' => $data['risk_score'] ?? 10,
            'risk_factors' => $data['risk_factors'] ?? [],
            'packaging_instruction' => $data['packaging_instruction'] ?? '',
            'fraud_prevention_checklist' => $data['fraud_prevention_checklist'] ?? [],
        ];
    }
}
