<?php

declare(strict_types=1);

namespace App\Actions\Logistics\Ai;

use App\Ai\Agents\SmartCarrierRouterAgent;
use App\Models\Order;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class RouteOrderShipment
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Order $order, float $packageDesi = 1.5): array
    {
        $agent = new SmartCarrierRouterAgent;

        $address = $order->shipping_address ?? [];
        $city = $address['city'] ?? 'İstanbul';
        $district = $address['district'] ?? 'Kadıköy';

        $prompt = sprintf(
            "Sipariş No: %s\nTeslimat Şehri/İlçesi: %s, %s\nPaket Desisi: %.2f\nSipariş Tutarı: %s\nMevcut Taşıyıcı Anlaşmaları: Trendyol Express (1-2 desi: 38 TL, SLA: 1 gün), HepsiJet (1-2 desi: 39 TL, SLA: 1 gün), Yurtiçi Kargo (1-2 desi: 52 TL, SLA: 1-2 gün), Sendeo (1-2 desi: 35 TL, SLA: 2 gün).",
            $order->remote_order_number ?? (string) $order->id,
            $city,
            $district,
            $packageDesi,
            json_encode($order->totals ?? [], JSON_UNESCAPED_UNICODE)
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return [
            'order_id' => $order->id,
            'recommended_carrier' => $data['recommended_carrier'] ?? 'Trendyol Express',
            'estimated_cost' => $data['estimated_cost'] ?? 38.0,
            'estimated_delivery_days' => $data['estimated_delivery_days'] ?? 1,
            'cost_savings_vs_default' => $data['cost_savings_vs_default'] ?? 0.0,
            'routing_reason' => $data['routing_reason'] ?? '',
            'alternatives' => $data['alternatives'] ?? [],
        ];
    }
}
