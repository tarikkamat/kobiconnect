<?php

declare(strict_types=1);

use App\Models\Brand;
use App\Models\ChannelListing;
use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    // Listeleme yaratmak outbox tetikleyicisini calistirir; push kuyrugu bu
    // dosyanin konusu degil.
    Queue::fake();

    $this->seed(TenantRoleSeeder::class);
    $this->grantActiveLicense();
});

function productCreateUser(string $role = 'Yönetici'): User
{
    /** @var User $user */
    $user = User::factory()->create()->assignRole($role);

    return $user;
}

it('serves the create form with brands, categories and statuses', function (): void {
    Brand::factory()->create(['name' => 'Kobi']);

    $this->actingAs(productCreateUser())
        ->get(route('products.create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/products/create')
            ->has('brands', 1)
            ->has('statuses', 3)
        );
});

it('creates a product with its variants, price and stock', function (): void {
    $warehouse = Warehouse::factory()->create(['is_default' => true]);

    $this->actingAs(productCreateUser())
        ->post(route('products.store'), [
            'name' => 'Şarj Aleti',
            'description' => 'Hızlı şarj',
            'status' => 'active',
            'variants' => [
                ['sku' => 'SRJ-1', 'barcode' => '8690000000001', 'list_price' => '149.90', 'on_hand' => '12'],
                ['sku' => 'SRJ-2', 'barcode' => null, 'list_price' => null, 'on_hand' => null],
            ],
        ])
        ->assertRedirect();

    $product = Product::query()->firstOrFail();

    expect($product->name)->toBe('Şarj Aleti')
        ->and($product->variants()->count())->toBe(2)
        ->and(Price::query()->count())->toBe(1)
        ->and(InventoryItem::query()->where('warehouse_id', $warehouse->getKey())->value('on_hand'))->toBe(12);
});

it('rejects a product without variants', function (): void {
    $this->actingAs(productCreateUser())
        ->post(route('products.store'), ['name' => 'Varyantsız', 'status' => 'draft'])
        ->assertSessionHasErrors('variants');

    expect(Product::query()->count())->toBe(0);
});

it('rejects a duplicate sku inside the same form', function (): void {
    $this->actingAs(productCreateUser())
        ->post(route('products.store'), [
            'name' => 'Tekrar',
            'status' => 'draft',
            'variants' => [
                ['sku' => 'AYNI'],
                ['sku' => 'AYNI'],
            ],
        ])
        ->assertSessionHasErrors('variants.0.sku');
});

it('does not let a warehouse worker create a product', function (): void {
    $this->actingAs(productCreateUser('Depo'))
        ->post(route('products.store'), [
            'name' => 'Yasak',
            'status' => 'draft',
            'variants' => [['sku' => 'YSK-1']],
        ])
        ->assertForbidden();
});

it('refuses to delete a listed product until the warning is acknowledged', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();
    ChannelListing::factory()->create(['variant_id' => $variant->getKey()]);

    $this->actingAs(productCreateUser())
        ->delete(route('products.destroy', $product))
        ->assertSessionHasErrors('acknowledge_listings');

    expect(Product::query()->count())->toBe(1);

    $this->actingAs(productCreateUser())
        ->delete(route('products.destroy', $product), ['acknowledge_listings' => true])
        ->assertRedirect(route('products.index'));

    expect(Product::query()->count())->toBe(0);
});

it('shows the listing count on the detail screen so deletion is never silent', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();
    ChannelListing::factory()->count(2)->create(['variant_id' => $variant->getKey()]);

    $this->actingAs(productCreateUser())
        ->get(route('products.show', $product))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('product.listingCount', 2));
});

it('previews how many variants a bulk price change touches before writing', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'BLK-1']);
    Price::factory()->create(['variant_id' => $variant->getKey(), 'list_price' => 100]);

    $response = $this->actingAs(productCreateUser())
        ->postJson(route('products.bulk-preview'), [
            'product_ids' => [$product->getKey()],
            'field' => 'price',
            'mode' => 'percent',
            'value' => 10,
        ])
        ->assertOk();

    expect($response->json('affected'))->toBe(1)
        ->and($response->json('samples.0.sku'))->toBe('BLK-1')
        ->and($response->json('samples.0.next'))->toContain('110,00');

    // Onizleme YAZMAZ.
    expect((float) Price::query()->value('list_price'))->toBe(100.0);
});

it('applies the same numbers the preview promised', function (): void {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();
    Price::factory()->create(['variant_id' => $variant->getKey(), 'list_price' => 100]);

    $this->actingAs(productCreateUser())
        ->post(route('products.bulk-update'), [
            'product_ids' => [$product->getKey()],
            'field' => 'price',
            'mode' => 'percent',
            'value' => 10,
        ])
        ->assertRedirect();

    expect((float) Price::query()->value('list_price'))->toBe(110.0);
});

it('does not let a warehouse worker bulk edit prices', function (): void {
    $product = Product::factory()->create();

    $this->actingAs(productCreateUser('Depo'))
        ->post(route('products.bulk-update'), [
            'product_ids' => [$product->getKey()],
            'field' => 'price',
            'mode' => 'set',
            'value' => 1,
        ])
        ->assertForbidden();
});
