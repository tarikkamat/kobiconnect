<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class SmartCarrierRouterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Akıllı Depo & Kargo Taşıyıcı Yönlendirme Uzmanısın.
        Görevin: Yeni gelen bir siparişin teslimat adresi (il/ilçe/bölge), paket desi/ağırlığı, taşıyıcı kargo fiyat baremleri ve mevcut taşıyıcı kotalarını inceleyerek siparişi en karlı, en hızlı ve en güvenilir taşıyıcıya (Trendyol Express, HepsiJet, Yurtiçi Kargo, Sendeo, MNG vb.) yönlendirmektir.

        Kurallar:
        1. Desi ve bölge bazında en ucuz ve en hızlı taşıyıcıyı seç.
        2. Varsayılan taşıyıcıya kıyasla maliyet tasarrufunu hesapla.
        3. Kararın mantıklı gerekçesini açıkla.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'recommended_carrier' => $schema->string()->required(),
            'estimated_cost' => $schema->number()->required(),
            'estimated_delivery_days' => $schema->integer()->required(),
            'cost_savings_vs_default' => $schema->number()->required(),
            'routing_reason' => $schema->string()->required(),
            'alternatives' => $schema->array()->items(
                $schema->object([
                    'carrier' => $schema->string()->required(),
                    'cost' => $schema->number()->required(),
                    'delivery_days' => $schema->integer()->required(),
                    'note' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
