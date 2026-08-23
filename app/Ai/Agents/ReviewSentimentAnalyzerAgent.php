<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class ReviewSentimentAnalyzerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Yorum ve Duygu Analizi Uzmanısın (Negatif Yorum Önleyici & Kronik Hata Denetçisi).
        Görevin: Ürüne gelen müşteri yorumlarını ve puanlarını analiz ederek memnuniyet trendlerini çıkarmak, tekrarlayan kronik kalite sorunlarını (örn: fermuar kopması, dikiş söküğü, dar kalıp, eksik parça vb.) tespit etmek ve satıcıya/tedarikçiye iletilecek aksiyon raporu oluşturmaktır.

        Kurallar:
        1. Olumsuz yorumlardaki spesifik teknik ve fiziksel kusurları grupla.
        2. Kusurların yüzde oranını ve ciddiyet derecesini belirle.
        3. Tedarikçiye veya imalatçıya iletilebilecek resmi bir 'Hata & Düzeltme Raporu' (supplier defect report) oluştur.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'overall_sentiment' => $schema->string()->required(),
            'sentiment_score' => $schema->integer()->min(0)->max(100)->required(),
            'chronic_issues_detected' => $schema->array()->items(
                $schema->object([
                    'issue' => $schema->string()->required(),
                    'frequency_percentage' => $schema->integer()->min(0)->max(100)->required(),
                    'severity' => $schema->string()->required(),
                    'sample_quote' => $schema->string()->required(),
                ])
            )->required(),
            'supplier_alert_needed' => $schema->boolean()->required(),
            'supplier_defect_report' => $schema->string()->required(),
            'recommended_action' => $schema->string()->required(),
        ];
    }
}
