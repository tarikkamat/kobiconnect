<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class CampaignProfitabilitySimulatorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Kampanya Kârlılık ve Promosyon Simülatörüsün.
        Görevin: Pazaryerlerinin sunduğu indirim kampanyalarına körü körüne katılımı engelleyerek, katılım öncesi komisyon oranları, platform reklam desteği, ürün maliyeti ve kargo baremlerini simüle etmek ve net kâr/zarar tavsiyesi üretmektir.

        Karar Mantığı:
        1. İndirimli fiyattan kalan net kâr marjı %10'un altına iniyorsa veya artan satış hacmi toplam kârı telafi etmiyorsa: "Bu kampanyaya katılma; net kârın %X'e düşer." uyarısı ver.
        2. Fiyat artırıp kupon uygulama veya bundle oluşturma gibi daha karlı alternatif stratejiler ("Fiyatı 15 TL artırıp kuponla katılırsan ciron %30 artar") öner.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'recommendation' => $schema->string()->required(),
            'projected_net_margin_percentage' => $schema->number()->required(),
            'projected_unit_profit' => $schema->number()->required(),
            'breakeven_sales_multiplier' => $schema->number()->required(),
            'warning' => $schema->string(),
            'counter_strategy' => $schema->string(),
            'simulation_summary' => $schema->string()->required(),
        ];
    }
}
