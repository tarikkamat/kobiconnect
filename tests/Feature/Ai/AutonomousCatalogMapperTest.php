<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Actions\Catalog\Ai\MapCatalogWithAi;
use App\Ai\Agents\AutonomousCatalogMapperAgent;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

it('maps product attributes autonomously without manual rules', function (): void {
    AutonomousCatalogMapperAgent::fake([
        [
            'suggested_category' => 'Kadın Tişört',
            'target_marketplace' => 'trendyol',
            'extracted_specs' => [
                'color' => 'Siyah',
                'size' => 'M',
                'material' => 'Pamuk',
                'fabric' => 'Örme',
                'gender' => 'Kadın',
                'pattern' => 'Düz',
                'fit' => 'Oversize',
            ],
            'attributes' => [
                [
                    'name' => 'Renk',
                    'value' => 'Siyah',
                    'marketplace_attribute_id' => 338,
                    'marketplace_attribute_value_id' => 12,
                    'confidence' => 95,
                    'reason' => 'Başlık ve görselden tespit edildi',
                ],
                [
                    'name' => 'Materyal',
                    'value' => 'Pamuk',
                    'marketplace_attribute_id' => 342,
                    'marketplace_attribute_value_id' => 88,
                    'confidence' => 90,
                    'reason' => 'Açıklamadaki %100 pamuk ifadesinden çıkarıldı',
                ],
            ],
        ],
    ]);

    $category = Category::factory()->create(['name' => 'Tişört']);
    $product = Product::factory()->create([
        'name' => 'Kadın Siyah Oversize Tişört',
        'description' => '%100 organik pamuk kumaştan üretilmiştir.',
        'category_id' => $category->id,
        'attributes' => [],
    ]);

    $action = new MapCatalogWithAi;
    $results = $action([$product], 'trendyol');

    expect($results)->toHaveCount(1)
        ->and($results[0]['suggested_category'])->toBe('Kadın Tişört')
        ->and($results[0]['extracted_specs']['color'])->toBe('Siyah')
        ->and($results[0]['attributes'])->toHaveCount(2);

    $product->refresh();
    /** @var array<string, mixed> $attrs */
    $attrs = is_array($product->attributes) ? $product->attributes : [];
    expect($attrs)->toHaveKey('color')
        ->and($attrs['color'])->toBe('Siyah')
        ->and($attrs['Materyal'])->toBe('Pamuk');
});

it('provides ai zero-config preview endpoint via api', function (): void {
    AutonomousCatalogMapperAgent::fake([
        [
            'suggested_category' => 'Telefon Kılıfı',
            'target_marketplace' => 'hepsiburada',
            'extracted_specs' => [
                'color' => 'Şeffaf',
                'size' => 'iPhone 15 Pro',
                'material' => 'Silikon',
                'fabric' => null,
                'gender' => 'Unisex',
                'pattern' => 'Düz',
                'fit' => 'Tam Uyum',
            ],
            'attributes' => [
                [
                    'name' => 'Uyumlu Model',
                    'value' => 'iPhone 15 Pro',
                    'marketplace_attribute_id' => 501,
                    'marketplace_attribute_value_id' => 102,
                    'confidence' => 99,
                    'reason' => 'Başlıktan çıkarıldı',
                ],
            ],
        ],
    ]);

    $user = User::factory()->create()->assignRole('Yönetici');

    $response = $this->actingAs($user)->postJson(route('ai.catalog.preview'), [
        'title' => 'iPhone 15 Pro Uyumlu Şeffaf Darbe Emici Silikon Kılıf',
        'description' => 'Köşe korumalı sararmaz şeffaf silikon malzeme.',
        'target_marketplace' => 'hepsiburada',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('preview.suggested_category', 'Telefon Kılıfı')
        ->assertJsonPath('preview.extracted_specs.material', 'Silikon');
});
