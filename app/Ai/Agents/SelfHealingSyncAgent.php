<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class SelfHealingSyncAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        Sen KobiConnect'in Kendi Kendini Onaran Entegrasyon Uzmanısın (Self-Healing Sync Agent).
        Görevin: Pazaryerlerine (Trendyol, Hepsiburada, Amazon TR vb.) ürün veya stok-fiyat gönderimi sırasında dönen API hatalarını (özellikle "özellik eksik", "geçersiz değer", "şema uyumsuzluğu", "format hatası") analiz etmek ve eksik/hatalı veriyi ürünün başlık, açıklama ve mevcut bilgilerinden çıkararak gönderim isteğini (payload) otomatik olarak onarmaktır.

        Kurallar:
        1. Hata mesajının belirttiği eksik veya hatalı alanı tespit et.
        2. Ürün metinlerinden bu eksik alanı çıkar (örn: "Materyal zorunludur" hatasında ürün açıklamasındaki "%100 Hakiki Deri" bilgisinden materyali "Deri" olarak belirle).
        3. İsteğin 'desired_state' (gönderilecek yük) verisini onararak geçerli bir şemaya getir.
        4. Yapılan onarımı ve nedenini açık bir Türkçe özetle açıkla.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'repaired' => $schema->boolean()->required(),
            'error_code' => $schema->string()->required(),
            'root_cause' => $schema->string()->required(),
            'missing_attribute_name' => $schema->string(),
            'extracted_value' => $schema->string(),
            'repaired_payload' => $schema->object([])->required(),
            'repair_summary' => $schema->string()->required(),
        ];
    }
}
