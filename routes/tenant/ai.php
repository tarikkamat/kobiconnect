<?php

declare(strict_types=1);

use App\Http\Controllers\Ai\CopilotController;
use App\Http\Controllers\Catalog\AiCatalogMappingController;
use App\Http\Controllers\Catalog\AiOptimizationController;
use App\Http\Controllers\Channels\AiSelfHealingController;
use App\Http\Controllers\Communication\AiQuestionReviewController;
use App\Http\Controllers\Logistics\AiLogisticsController;
use App\Http\Controllers\Pricing\AiPricingCampaignController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI-Native Routes
|--------------------------------------------------------------------------
|
| `routes/tenant.php` içinden ['auth', 'verified'] grubunda yüklenir.
|
*/

Route::prefix('ai')->name('ai.')->group(function (): void {
    // 1. KobiConnect Copilot (Operasyonel Ajan)
    Route::post('copilot/chat', [CopilotController::class, 'chat'])->name('copilot.chat');
    Route::get('copilot/conversations', [CopilotController::class, 'conversations'])->name('copilot.conversations');

    // 2. Otonom Katalog Eşleme (Zero-Config Onboarding)
    Route::post('catalog/map', [AiCatalogMappingController::class, 'map'])->name('catalog.map');
    Route::post('catalog/preview', [AiCatalogMappingController::class, 'preview'])->name('catalog.preview');

    // 3. Görsel & İçerik Optimizasyonu (SEO & Stüdyo)
    Route::post('catalog/products/{product}/seo', [AiOptimizationController::class, 'seo'])->name('catalog.seo');
    Route::post('catalog/products/{product}/image', [AiOptimizationController::class, 'image'])->name('catalog.image');
    Route::post('catalog/generate-image', [AiOptimizationController::class, 'generateImage'])->name('catalog.generate-image');

    // 4. Kendi Kendini Onaran Entegrasyon (Self-Healing Sync)
    Route::post('channels/operations/{operation}/heal', [AiSelfHealingController::class, 'heal'])->name('channels.heal');

    // 5. Müşteri İletişimi & Soru-Cevap & Yorum Analizi
    Route::post('communication/answer', [AiQuestionReviewController::class, 'answerQuestion'])->name('communication.answer');
    Route::post('communication/reviews', [AiQuestionReviewController::class, 'analyzeReviews'])->name('communication.reviews');

    // 6. Lojistik, Desi Tahkimi & İade Risk Skorlama
    Route::get('logistics/desi-audit', [AiLogisticsController::class, 'auditDesi'])->name('logistics.desi-audit');
    Route::post('logistics/orders/{order}/risk', [AiLogisticsController::class, 'scoreRisk'])->name('logistics.risk');
    Route::post('logistics/orders/{order}/route', [AiLogisticsController::class, 'routeCarrier'])->name('logistics.route');

    // 7. Fiyatlama, Tedarik & Kampanya Zekası
    Route::post('pricing/variants/{variant}/dynamic-price', [AiPricingCampaignController::class, 'dynamicPrice'])->name('pricing.dynamic-price');
    Route::post('pricing/variants/{variant}/forecast-stock', [AiPricingCampaignController::class, 'forecastStock'])->name('pricing.forecast-stock');
    Route::post('pricing/products/{product}/simulate-campaign', [AiPricingCampaignController::class, 'simulateCampaign'])->name('pricing.simulate-campaign');
});
