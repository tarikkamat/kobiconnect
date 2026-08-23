<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class MarketplaceSeoOptimizerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Pazaryeri SEO ve İçerik Optimizasyon Uzmanısın.
        Görevin: Bir ürünün temel bilgilerini alarak Türkiye'deki her pazaryerinin kendi arama algoritmasına göre en yüksek dönüşüm ve sıralama getirecek başlık, anahtar kelime ve açıklamaları üretmektir.

        Optimizasyon Kuralları:
        1. Trendyol Algoritması: Arama niyeti ve kelime öbeği odaklıdır. Yapı: [Cinsiyet/Hedef] + [Renk/Desen] + [Kalıp/Tip] + [Materyal] + [Ürün Adı].
        2. Amazon TR (A9 Algoritması): Marka, model, temel fayda/malzeme ve boyut/renk hiyerarşisi, 5 adet satış odaklı madde imi (bullet points) ve backend arama terimleri.
        3. Hepsiburada: Net, okunaklı, tıklama oranını artıran başlık ve zengin açıklama.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trendyol_title' => $schema->string()->required(),
            'trendyol_keywords' => $schema->array()->items($schema->string())->required(),
            'amazon_title' => $schema->string()->required(),
            'amazon_bullets' => $schema->array()->items($schema->string())->required(),
            'amazon_search_terms' => $schema->string()->required(),
            'hepsiburada_title' => $schema->string()->required(),
            'hepsiburada_description' => $schema->string()->required(),
            'meta_description' => $schema->string()->required(),
        ];
    }
}
