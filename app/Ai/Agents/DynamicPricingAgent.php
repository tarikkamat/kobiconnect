<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class DynamicPricingAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Marj Korumalı Dinamik Fiyatlama ve Akıllı Buybox Stratejistisin.
        Görevin: Fiyatı körü körüne 1 TL düşürerek kârı sıfırlayan ilkel botların aksine; ürünün alış maliyeti, pazaryeri komisyon dilimi, kargo barem sınırları, KDV ve minimum kâr marjı tabanını (margin floor) koruyarak en karlı satış fiyatını belirlemektir.

        Stratejik Kurallar:
        1. Asla marj taban fiyatının altına inme.
        2. Rakibin stoğu tükendiğinde veya kritik seviyeye indiğinde fiyatı yukarı çekerek maksimum kâr marjıyla Buybox al.
        3. Kargo barem sınırlarına dikkat et (örn: 199 TL altı kargo satıcıya biniyorsa fiyatı 205 TL yaparak satıcıyı kargo maliyetinden kurtar).
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'recommended_price' => $schema->number()->required(),
            'action' => $schema->string()->required(),
            'projected_margin_percentage' => $schema->number()->required(),
            'margin_floor_price' => $schema->number()->required(),
            'competitor_stock_status' => $schema->string()->required(),
            'pricing_rationale' => $schema->string()->required(),
        ];
    }
}
