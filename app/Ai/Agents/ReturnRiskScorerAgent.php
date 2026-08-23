<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class ReturnRiskScorerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in İade Tahmin, Risk Skorlama ve Sahte İade Önleme Uzmanısın.
        Görevin: Bir siparişi analiz ederek (müşterinin geçmiş iade sıklığı, ürünün beden uyumsuzluk/hasar geçmişi, teslimat bölgesi, sipariş tutarı vb.) iade risk skorunu hesaplamaktır.

        Kurallar:
        1. Yüksek risk taşıyan siparişlerde paketleme ekibine özel talimat üret (örn: "Bu müşteriye beden teyit notu ekle", "Güvenlik kilidi tak", "Kutuyu çift kat patpata sar").
        2. Olası sahte/kullanılmış/hasarlı iadeleri önlemek ve kanıt oluşturmak için kargo çıkışında ve iade kabulünde uygulanacak fotoğraf kontrol listesi (fraud prevention checklist) belirle.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'risk_level' => $schema->string()->required(),
            'risk_score' => $schema->integer()->min(0)->max(100)->required(),
            'risk_factors' => $schema->array()->items($schema->string())->required(),
            'packaging_instruction' => $schema->string()->required(),
            'fraud_prevention_checklist' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
