<?php

declare(strict_types=1);

namespace App\Actions\Communication\Ai;

use App\Ai\Agents\CustomerQuestionAnswererAgent;
use App\Models\Category;
use App\Models\Product;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class AutoAnswerCustomerQuestion
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $questionBody, ?Product $product = null): array
    {
        $agent = new CustomerQuestionAnswererAgent;

        $productContext = 'Genel Mağaza / Belirtilmemiş Ürün';
        if ($product) {
            $categoryName = $product->category instanceof Category ? $product->category->name : 'Genel';
            $productContext = sprintf(
                "Ürün Adı: %s\nAçıklama: %s\nKategori: %s\nNitelikler/Özellikler: %s\nVaryantlar/Ebatlar: %s",
                $product->name,
                $product->description ?? 'Yok',
                $categoryName,
                json_encode($product->attributes ?? [], JSON_UNESCAPED_UNICODE),
                json_encode($product->variants->map(fn ($v) => [
                    'sku' => $v->sku,
                    'attributes' => $v->attributes,
                    'dimensions' => $v->dimensions,
                ])->all(), JSON_UNESCAPED_UNICODE)
            );
        }

        $prompt = sprintf(
            "Müşteri Sorusu: %s\n\nÜrün Veritabanı Bilgileri:\n%s\n\nLütfen bu soruya en doğru, pazaryeri onaylı ve nazik yanıtı üret.",
            $questionBody,
            $productContext
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return [
            'question' => $questionBody,
            'product_id' => $product?->id,
            'answer' => $data['answer'] ?? 'Bilgi bulunamadı.',
            'confidence' => $data['confidence'] ?? 0,
            'is_safe_to_auto_reply' => $data['is_safe_to_auto_reply'] ?? false,
            'grounded_facts_used' => $data['grounded_facts_used'] ?? [],
            'suggested_action' => $data['suggested_action'] ?? 'needs_human_approval',
        ];
    }
}
