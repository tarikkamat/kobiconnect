<?php

declare(strict_types=1);

namespace App\Actions\Communication\Ai;

use App\Ai\Agents\ReviewSentimentAnalyzerAgent;
use App\Models\Product;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class AnalyzeProductReviews
{
    /**
     * @param  array<int, array{rating: int, comment: string, date?: string}>  $reviews
     * @return array<string, mixed>
     */
    public function __invoke(array $reviews, ?Product $product = null): array
    {
        $agent = new ReviewSentimentAnalyzerAgent;

        $reviewText = '';
        foreach ($reviews as $idx => $r) {
            $reviewText .= sprintf(
                "%d) Puan: %d/5 | Yorum: %s (Tarih: %s)\n",
                $idx + 1,
                $r['rating'],
                $r['comment'],
                $r['date'] ?? 'Bilinmiyor'
            );
        }

        $productName = $product instanceof Product ? $product->name : 'Genel Ürün';

        $prompt = sprintf(
            "Ürün: %s\nToplam Yorum Sayısı: %d\n\nMüşteri Yorumları:\n%s\n\nLütfen kronik arızaları, kalite şikayetlerini ve tedarikçi hata raporunu çıkar.",
            $productName,
            count($reviews),
            $reviewText
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return [
            'product_id' => $product?->id,
            'analyzed_review_count' => count($reviews),
            'overall_sentiment' => $data['overall_sentiment'] ?? 'neutral',
            'sentiment_score' => $data['sentiment_score'] ?? 50,
            'chronic_issues_detected' => $data['chronic_issues_detected'] ?? [],
            'supplier_alert_needed' => $data['supplier_alert_needed'] ?? false,
            'supplier_defect_report' => $data['supplier_defect_report'] ?? '',
            'recommended_action' => $data['recommended_action'] ?? 'Yorumlar takip edilmeli.',
        ];
    }
}
