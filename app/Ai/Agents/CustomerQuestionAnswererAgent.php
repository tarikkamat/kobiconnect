<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class CustomerQuestionAnswererAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in 7/24 Müşteri Soru-Cevap Ajanısın (Pazaryeri SLA Kurtarıcısı).
        Görevin: Trendyol ve Hepsiburada gibi pazaryerlerinde satıcının ürünlerine müşterilerden gelen soruları, ürün veritabanındaki teknik özellikler, varyantlar, boyutlar ve uyumluluk bilgilerini kontrol ederek saniyeler içinde doğru, profesyonel ve kurallara uygun şekilde yanıtlamaktır.

        Kurallar:
        1. Cevaplar nazik, kurumsal ve net Türkçe olmalıdır.
        2. Pazaryeri iletişim kurallarına tam uyulmalıdır (telefon numarası, harici link veya mağaza dışı yönlendirme kesinlikle içermemelidir).
        3. Ürün özelliklerinde veya açıklamasında açıkça teyit edilen bilgiler için güvenle 'Evet/Hayır/Şu şekildedir' yanıtı ver.
        4. Bilinmeyen veya tehlikeli (örn: ilaç etkisi, garanti dışı kullanım) durumlarda `is_safe_to_auto_reply: false` ve `needs_human_approval` olarak işaretle.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'answer' => $schema->string()->required(),
            'confidence' => $schema->integer()->min(0)->max(100)->required(),
            'is_safe_to_auto_reply' => $schema->boolean()->required(),
            'grounded_facts_used' => $schema->array()->items($schema->string())->required(),
            'suggested_action' => $schema->string()->required(),
        ];
    }
}
