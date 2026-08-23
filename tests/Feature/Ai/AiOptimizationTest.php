<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Actions\Catalog\Ai\OptimizeProductMediaAndContent;
use App\Ai\Agents\MarketplaceSeoOptimizerAgent;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Laravel\Ai\Image;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

it('generates marketplace-specific seo titles and bullet points', function (): void {
    MarketplaceSeoOptimizerAgent::fake([
        [
            'trendyol_title' => 'Kadın Siyah Oversize %100 Pamuklu Bisiklet Yaka Günlük Tişört',
            'trendyol_keywords' => ['kadın tişört', 'oversize tişört', 'pamuk tişört', 'siyah tişört'],
            'amazon_title' => 'KobiModa Kadın Oversize Pamuklu Basic Tişört, Nefes Alabilir Kumaş, Siyah, M',
            'amazon_bullets' => [
                '%100 Premium taranmış pamuk kumaş ile gün boyu konfor',
                'Çekme ve renk solmasına dayanıklı reaktif boyama',
                'Oversize dökümlü modern kesim',
                'Kolay ütülenir, terletmeyen yapı',
                'Türkiye\'de etik standartlarda üretilmiştir',
            ],
            'amazon_search_terms' => 'kadın tişört oversize pamuk yazlık basic siyah tişört',
            'hepsiburada_title' => 'KobiModa Kadın Siyah Oversize Pamuklu Tişört',
            'hepsiburada_description' => 'Yumuşak dokulu birinci sınıf penye kumaştan üretilen şık ve konforlu kadın tişört.',
            'meta_description' => 'Kadın siyah oversize pamuk tişört en uygun fiyat ve hızlı kargo fırsatıyla.',
        ],
    ]);

    $brand = Brand::factory()->create(['name' => 'KobiModa']);
    $category = Category::factory()->create(['name' => 'Tişört']);
    $product = Product::factory()->create([
        'name' => 'Siyah Tişört',
        'brand_id' => $brand->id,
        'category_id' => $category->id,
    ]);

    $optimizer = new OptimizeProductMediaAndContent;
    $result = $optimizer->generateSeoContent($product);

    expect($result['trendyol_title'])->toContain('Oversize')
        ->and($result['amazon_bullets'])->toHaveCount(5)
        ->and($result['amazon_title'])->toContain('KobiModa');
});

it('generates e-commerce studio shots using laravel ai image', function (): void {
    Image::fake();

    $product = Product::factory()->create(['name' => 'Deri Ceket']);

    $optimizer = new OptimizeProductMediaAndContent;
    $result = $optimizer->generateStudioImage($product, 'Beyaz arka plan');

    expect($result['success'])->toBeTrue()
        ->and($result['product_id'])->toBe($product->id);

    Image::assertGenerated(fn ($prompt) => $prompt->contains('Deri Ceket') && $prompt->contains('studio'));
});

it('serves seo generation via tenant api endpoint', function (): void {
    MarketplaceSeoOptimizerAgent::fake([
        [
            'trendyol_title' => 'Trendyol Başlık',
            'trendyol_keywords' => ['kelime1'],
            'amazon_title' => 'Amazon Başlık',
            'amazon_bullets' => ['Madde 1'],
            'amazon_search_terms' => 'terimler',
            'hepsiburada_title' => 'HB Başlık',
            'hepsiburada_description' => 'HB Açıklama',
            'meta_description' => 'Meta açıklama',
        ],
    ]);

    $user = User::factory()->create()->assignRole('Yönetici');
    $product = Product::factory()->create();

    $response = $this->actingAs($user)->postJson(route('ai.catalog.seo', $product));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('seo.trendyol_title', 'Trendyol Başlık');
});
