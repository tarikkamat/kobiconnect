<?php

declare(strict_types=1);

use App\Enums\ListingSyncState;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Brand;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\InventoryItem;
use App\Models\Price;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

/**
 * @return User
 */
function catalogUser(string $role)
{
    return User::factory()->create()->assignRole($role);
}

it('lists products with stock and price already formatted', function (): void {
    $product = Product::factory()->create(['name' => 'Termos']);
    $variant = ProductVariant::factory()->for($product)->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'on_hand' => 12, 'reserved' => 2]);
    Price::factory()->create(['variant_id' => $variant->id, 'list_price' => 149.9]);

    $this->actingAs(catalogUser('Yönetici'))
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('catalog/products/index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Termos')
            ->where('products.data.0.stock', 10)
            ->where('products.data.0.price', fn (string $price): bool => str_contains($price, '149,90'))
        );
});

it('searches through the turkish full text vector regardless of diacritics', function (): void {
    Product::factory()->create(['name' => 'Şarj Aleti']);
    Product::factory()->create(['name' => 'Klavye']);

    $this->actingAs(catalogUser('Yönetici'))
        ->get(route('products.index', ['search' => 'sarj']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Şarj Aleti')
        );
});

it('filters by status and stock availability', function (): void {
    $inStock = Product::factory()->active()->create(['name' => 'Stoklu']);
    $variant = ProductVariant::factory()->for($inStock)->create();
    InventoryItem::factory()->create(['variant_id' => $variant->id, 'on_hand' => 5]);

    Product::factory()->active()->create(['name' => 'Stoksuz']);
    Product::factory()->create(['name' => 'Taslak']);

    $user = catalogUser('Yönetici');

    $this->actingAs($user)
        ->get(route('products.index', ['status' => 'active']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 2));

    $this->actingAs($user)
        ->get(route('products.index', ['stock' => 'var']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Stoklu')
        );

    $this->actingAs($user)
        ->get(route('products.index', ['stock' => 'yok']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 2));
});

it('rejects an unknown sort column instead of interpolating it', function (): void {
    $this->actingAs(catalogUser('Yönetici'))
        ->get(route('products.index', ['sort' => 'id; drop table products']))
        ->assertSessionHasErrors('sort');
});

it('keeps the images tab out of the initial payload', function (): void {
    $product = Product::factory()->create();
    ProductImage::factory()->create(['product_id' => $product->id]);

    $response = $this->actingAs(catalogUser('Yönetici'))->get(route('products.show', $product));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('catalog/products/show')
        ->has('variants')
        ->missing('images')
    );

    // <WhenVisible> kismi yeniden yukleme yapinca gelir. Yanit XHR JSON'udur,
    // assertInertia yalnizca view yanitlarini okur. Version header uyusmazsa
    // Inertia 409 doner, bu yuzden middleware'in hesapladigi deger gonderilir.
    $this->actingAs(catalogUser('Yönetici'))
        ->get(route('products.show', $product), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'catalog/products/show',
            'X-Inertia-Partial-Data' => 'images',
        ])
        ->assertOk()
        ->assertJsonCount(1, 'props.images');
});

it('updates a product for a user who may manage the catalog', function (): void {
    $product = Product::factory()->create();
    $brand = Brand::factory()->create();

    $this->actingAs(catalogUser('Yönetici'))
        ->patch(route('products.update', $product), [
            'name' => 'Yeni ad',
            'description' => 'Açıklama',
            'brand_id' => $brand->id,
            'category_id' => null,
            'status' => 'active',
        ])
        ->assertRedirect();

    expect($product->refresh()->name)->toBe('Yeni ad')
        ->and($product->brand_id)->toBe($brand->id)
        ->and($product->status->value)->toBe('active');
});

it('lets the warehouse role read the catalog but not edit it', function (): void {
    $product = Product::factory()->create(['name' => 'Dokunulmaz']);
    $user = catalogUser('Depo');

    $this->actingAs($user)->get(route('products.index'))->assertOk();

    $this->actingAs($user)
        ->patch(route('products.update', $product), [
            'name' => 'Degistirildi',
            'status' => 'active',
        ])
        ->assertForbidden();

    expect($product->refresh()->name)->toBe('Dokunulmaz');
});

it('collapses variant listings into one avatar per channel with the worst state', function (): void {
    // Listeleme kaydi olusturmak pazaryerine push tetikler; bu test yalnizca
    // listedeki avatarla ilgileniyor.
    Http::fake();

    $connection = ChannelConnection::factory()->create([
        'marketplace' => 'trendyol',
        'name' => 'Trendyol Ana',
    ]);

    $product = Product::factory()->create(['name' => 'Termos']);
    $first = ProductVariant::factory()->for($product)->create();
    $second = ProductVariant::factory()->for($product)->create();

    ChannelListing::factory()->create([
        'connection_id' => $connection->getKey(),
        'variant_id' => $first->getKey(),
        'sync_state' => ListingSyncState::Synced,
    ]);
    ChannelListing::factory()->create([
        'connection_id' => $connection->getKey(),
        'variant_id' => $second->getKey(),
        'sync_state' => ListingSyncState::Failed,
    ]);

    $this->actingAs(catalogUser('Yönetici'))
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data.0.channels', 1)
            ->where('products.data.0.channels.0.marketplace', 'trendyol')
            ->where('products.data.0.channels.0.name', 'Trendyol Ana')
            ->where('products.data.0.channels.0.state', 'failed')
        );
});

it('sends no channels for a product that is on no marketplace', function (): void {
    ProductVariant::factory()->for(Product::factory()->create())->create();

    $this->actingAs(catalogUser('Yönetici'))
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('products.data.0.channels', 0)
        );
});
