<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Actions\Communication\Ai\AnalyzeProductReviews;
use App\Actions\Communication\Ai\AutoAnswerCustomerQuestion;
use App\Ai\Agents\CustomerQuestionAnswererAgent;
use App\Ai\Agents\ReviewSentimentAnalyzerAgent;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

it('answers customer questions accurately based on product specs', function (): void {
    CustomerQuestionAnswererAgent::fake([
        [
            'answer' => 'Merhabalar, evet kılıfımız iPhone 15 Pro Max modeli ile birebir uyumludur. Kamera lens çıkıntısını ve tuşları tam koruyacak şekilde tasarlanmıştır.',
            'confidence' => 99,
            'is_safe_to_auto_reply' => true,
            'grounded_facts_used' => ['iPhone 15 Pro Max uyumlu', 'Kamera korumalı yükseltilmiş çerçeve'],
            'suggested_action' => 'instant_reply',
        ],
    ]);

    $product = Product::factory()->create([
        'name' => 'iPhone 15 Pro Max Uyumlu Mat Silikon Kılıf',
        'attributes' => ['Uyumlu Model' => 'iPhone 15 Pro Max', 'Materyal' => 'Silikon'],
    ]);
    ProductVariant::factory()->for($product)->create(['sku' => 'IPH15PM-BLK']);

    $action = new AutoAnswerCustomerQuestion;
    $result = $action('Bu kılıf iPhone 15 Pro Max\'e uyar mı?', $product);

    expect($result['confidence'])->toBe(99)
        ->and($result['is_safe_to_auto_reply'])->toBeTrue()
        ->and($result['answer'])->toContain('birebir uyumludur');
});

it('analyzes reviews to catch chronic product failure patterns', function (): void {
    ReviewSentimentAnalyzerAgent::fake([
        [
            'overall_sentiment' => 'negative',
            'sentiment_score' => 25,
            'chronic_issues_detected' => [
                [
                    'issue' => 'Fermuar kopması / sıkışması',
                    'frequency_percentage' => 80,
                    'severity' => 'Yüksek',
                    'sample_quote' => 'İlk kullanımda fermuar dişi kırıldı.',
                ],
            ],
            'supplier_alert_needed' => true,
            'supplier_defect_report' => "TEDARİKÇİ KALİTE UYARI RAPORU\nÜrün: Deri Sırt Çantası\nTespit: Son 15 gündeki olumsuz yorumların %80'i fermuar mekanizmasındaki metal dayanım yetersizliğini göstermektedir. Tip 5 metal fermuara geçilmesi zorunludur.",
            'recommended_action' => 'Ürünü geçici olarak incelemeye al ve tedarikçiden fermuar revizyonu talep et.',
        ],
    ]);

    $product = Product::factory()->create(['name' => 'Deri Sırt Çantası']);
    $reviews = [
        ['rating' => 1, 'comment' => 'Fermuarı hemen koptu hiç beğenmedim', 'date' => '2026-08-10'],
        ['rating' => 1, 'comment' => 'İlk kullanımda fermuar dişi kırıldı', 'date' => '2026-08-12'],
        ['rating' => 2, 'comment' => 'Deri güzel ama fermuarı kapanmıyor', 'date' => '2026-08-15'],
    ];

    $action = new AnalyzeProductReviews;
    $analysis = $action($reviews, $product);

    expect($analysis['supplier_alert_needed'])->toBeTrue()
        ->and($analysis['chronic_issues_detected'][0]['frequency_percentage'])->toBe(80)
        ->and($analysis['supplier_defect_report'])->toContain('TEDARİKÇİ KALİTE UYARI RAPORU');
});

it('handles question answering through tenant api route', function (): void {
    CustomerQuestionAnswererAgent::fake([
        [
            'answer' => 'Evet efendim uygundur.',
            'confidence' => 95,
            'is_safe_to_auto_reply' => true,
            'grounded_facts_used' => ['Uyumluluk teyit edildi'],
            'suggested_action' => 'instant_reply',
        ],
    ]);

    $user = User::factory()->create()->assignRole('Yönetici');

    $response = $this->actingAs($user)->postJson(route('ai.communication.answer'), [
        'question' => 'Ürün su geçirmez mi?',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.answer', 'Evet efendim uygundur.');
});
