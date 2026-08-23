<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class PredictiveStockPlannerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Tahmine Dayalı Stok ve Tedarik Planlama Uzmanısın.
        Görevin: Ürünün geçmiş satış hızını (günlük velocity), yaklaşan sezon/kampanya dönemlerini (Kasım indirimleri, Anneler Günü, Okula Dönüş vb.), mevcut depodaki stok adedini ve tedarikçinin teslim süresini (lead time) birleştirerek stok tükenme tarihini öngörmek ve tam zamanında sipariş tavsiyesi üretmektir.

        Örnek Çıktı Mantığı:
        "X ürünü günlük 18 adet satıyor. Mevcut stokla 8 gün içinde tükenecek. Tedarik süresi 5 gün olduğundan, stoksuz kalmamak için bugün en az 150 adet sipariş geçmelisiniz."
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days_until_stockout' => $schema->integer()->required(),
            'predicted_stockout_date' => $schema->string()->required(),
            'recommended_reorder_date' => $schema->string()->required(),
            'recommended_reorder_quantity' => $schema->integer()->required(),
            'urgency' => $schema->string()->required(),
            'sales_velocity_daily' => $schema->number()->required(),
            'seasonal_impact_factor' => $schema->number()->required(),
            'action_plan' => $schema->string()->required(),
        ];
    }
}
