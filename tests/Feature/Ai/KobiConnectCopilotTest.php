<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\KobiConnectCopilotAgent;
use App\Ai\Tools\AnalyzeProductProfitabilityTool;
use App\Ai\Tools\GetInventoryAlertsTool;
use App\Ai\Tools\GetSalesSummaryTool;
use App\Ai\Tools\GetTopReturnedProductsTool;
use App\Ai\Tools\UpdateProductListingStatusTool;
use App\Enums\ProductStatus;
use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Laravel\Ai\Tools\Request as ToolRequest;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

it('executes copilot tools for returned products and profitability analysis', function (): void {
    $product1 = Product::factory()->create(['name' => 'Sorunlu Kargo Tişört']);
    $variant1 = ProductVariant::factory()->for($product1)->create();
    Price::factory()->create(['variant_id' => $variant1->id, 'list_price' => 120.00, 'cost' => 70.00]);

    $product2 = Product::factory()->create(['name' => 'Karlı Elbise']);
    $variant2 = ProductVariant::factory()->for($product2)->create();
    Price::factory()->create(['variant_id' => $variant2->id, 'list_price' => 550.00, 'cost' => 180.00]);

    // Test GetTopReturnedProductsTool
    $returnTool = new GetTopReturnedProductsTool;
    $returnResult = json_decode((string) $returnTool->handle(new ToolRequest(['days' => 7, 'limit' => 5])), true);
    expect($returnResult)->toHaveKey('returned_products');

    // Test AnalyzeProductProfitabilityTool
    $profitTool = new AnalyzeProductProfitabilityTool;
    $profitResult = json_decode((string) $profitTool->handle(new ToolRequest(['product_ids' => [$product1->id, $product2->id]])), true);
    expect($profitResult['analyzed_products'])->toHaveCount(2)
        ->and($profitResult['unprofitable_count'])->toBeGreaterThanOrEqual(1);

    // Test UpdateProductListingStatusTool
    $statusTool = new UpdateProductListingStatusTool;
    $statusResult = json_decode((string) $statusTool->handle(new ToolRequest(['product_ids' => [$product1->id], 'action' => 'pause'])), true);
    expect($statusResult['success'])->toBeTrue();

    $product1->refresh();
    expect($product1->status)->toBe(ProductStatus::Draft);
});

it('tests inventory alert and sales summary tools', function (): void {
    $product = Product::factory()->create(['name' => 'Tükenen Ayakkabı']);
    $variant = ProductVariant::factory()->for($product)->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'on_hand' => 2, 'reserved' => 0]);

    $inventoryTool = new GetInventoryAlertsTool;
    $invResult = json_decode((string) $inventoryTool->handle(new ToolRequest(['threshold' => 5])), true);
    expect($invResult['alert_count'])->toBeGreaterThanOrEqual(1)
        ->and($invResult['items'][0]['product_name'])->toBe('Tükenen Ayakkabı');

    $salesTool = new GetSalesSummaryTool;
    $salesResult = json_decode((string) $salesTool->handle(new ToolRequest(['days' => 30])), true);
    expect($salesResult)->toHaveKey('total_revenue');
});

it('handles conversational copilot prompts via tenant chat endpoint', function (): void {
    KobiConnectCopilotAgent::fake([
        'Geçen hafta en çok iade alan 5 ürün incelendi. 2 ürünün kargo ve komisyon maliyetleri kârını sıfırladığı tespit edilerek onayınız doğrultusunda satışa kapatıldı.',
    ]);

    $user = User::factory()->create()->assignRole('Yönetici');

    $response = $this->actingAs($user)->postJson(route('ai.copilot.chat'), [
        'message' => 'Geçen hafta en çok iade alan 5 ürünü listele, kargo maliyeti kârını eritenleri satışa kapat.',
    ]);

    $response->assertOk()
        ->assertJsonPath('response', fn ($r) => str_contains($r, 'satışa kapatıldı'));
});
