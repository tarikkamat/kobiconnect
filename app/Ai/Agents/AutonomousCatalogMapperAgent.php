<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class AutonomousCatalogMapperAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Otonom Katalog Eşleme Uzmanısın (Zero-Config Onboarding Agent).
        Görevin: Bir satıcı karmaşık XML, JSON veya ham katalog verisi yüklediğinde; ürünün başlığı, açıklaması, görselleri ve ham verilerini analiz ederek Türkiye'nin önde gelen pazaryerleri (Trendyol, Hepsiburada, Amazon TR, Çiçeksepeti) için zorunlu ve kritik nitelikleri (renk, beden, kumaş, materyal, yaka tipi, kalıp, cinsiyet vb.) tespit etmek ve eşlemektir.

        Kurallar:
        1. Başlık ve açıklamada açıkça geçen veya ima edilen renk, beden, materyal (pamuk, deri, polyester vb.) ve özellikleri çıkar.
        2. Güven skorunu 0-100 arasında belirt.
        3. Çıkarılan özellikleri standart ve temiz Türkçe terimlerle eşle.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'suggested_category' => $schema->string()->required(),
            'target_marketplace' => $schema->string()->required(),
            'extracted_specs' => $schema->object([
                'color' => $schema->string(),
                'size' => $schema->string(),
                'material' => $schema->string(),
                'fabric' => $schema->string(),
                'gender' => $schema->string(),
                'pattern' => $schema->string(),
                'fit' => $schema->string(),
            ])->required(),
            'attributes' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->required(),
                    'value' => $schema->string()->required(),
                    'marketplace_attribute_id' => $schema->integer(),
                    'marketplace_attribute_value_id' => $schema->integer(),
                    'confidence' => $schema->integer()->min(0)->max(100)->required(),
                    'reason' => $schema->string()->required(),
                ])
            )->required(),
        ];
    }
}
