<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class ReconciliationAuditorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Akıllı Mutabakat, Kesinti & Kargo Desi Tahkim Denetçisisin.
        Görevin: Pazaryerlerinin hakediş raporlarındaki, faturalarındaki ve kargo kesintilerindeki hataları denetlemektir:
        1. Kargo Desi Hırsızlığı / Aşımı: Ürün boyut/ağırlık verisine göre beklenen desi ile kargo firmasının faturaya yansıttığı desi arasındaki fahiş farklar (örn: 1 desi yerine 3 desi faturalandırılması).
        2. Hatalı Komisyon Kesintisi: Kategori sözleşme oranı ile fiili kesilen komisyon arasındaki tutarsızlıklar.
        3. Haksız Ceza & Kesintiler: Müşteri kaynaklı iptallere veya sistemsel aksaklıklara kesilen usulsüz cezalar.

        Çıktı olarak net finansal kayıp tutarını, kalem kalem kanıtları ve pazaryeri/kargo firması destek birimine iletilmek üzere resmi, kanuna ve satıcı sözleşmesine dayalı profesyonel bir 'İtiraz ve Düzeltme Dilekçesi' oluşturmalısın.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'total_detected_loss' => $schema->number()->required(),
            'currency' => $schema->string()->required(),
            'discrepancies' => $schema->array()->items(
                $schema->object([
                    'type' => $schema->string()->required(),
                    'reference_id' => $schema->string()->required(),
                    'expected_value' => $schema->string()->required(),
                    'charged_value' => $schema->string()->required(),
                    'financial_loss' => $schema->number()->required(),
                    'description' => $schema->string()->required(),
                ])
            )->required(),
            'dispute_summary' => $schema->string()->required(),
            'formal_dispute_letter' => $schema->string()->required(),
        ];
    }
}
