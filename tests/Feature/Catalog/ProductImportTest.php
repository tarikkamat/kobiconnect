<?php

declare(strict_types=1);

use App\Actions\Catalog\ImportProducts;
use App\Enums\ListingSyncState;
use App\Enums\ProcessingStatus;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncRun;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Hepsiburada\Fixture;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    Warehouse::factory()->create(['is_default' => true]);

    Http::fake([
        '*all-products-of-merchant*' => Http::response(Fixture::json('measured-products')),
    ]);
});

function hbConnection(): ChannelConnection
{
    return ChannelConnection::factory()->create([
        'marketplace' => 'hepsiburada',
        'credentials' => [
            'merchant_id' => 'c5779c28-af0a-43e1-a8a6-8b30782e79ec',
            'service_key' => 'test-secret',
            'integrator' => 'finansfatura_dev',
            'sit' => true,
        ],
    ]);
}

it('pulls products from marketplace and creates canonical models', function (): void {
    $connection = hbConnection();

    $stats = app(ImportProducts::class)->handle($connection, maxPages: 1);

    expect($stats['created'])->toBe(2)
        ->and($stats['matched'])->toBe(0)
        ->and($stats['total'])->toBe(2)
        ->and(Product::count())->toBe(2)
        ->and(ProductVariant::count())->toBe(2)
        ->and(ChannelListing::count())->toBe(2)
        ->and(Brand::count())->toBe(2);

    $product = Product::where('name', 'Daniel Klein 8680161820017 Kadın Kol Saati')->first();
    expect($product)->not->toBeNull()
        ->and($product->status)->toBe(ProductStatus::Active)
        ->and($product->brand->name)->toBe('Daniel Klein');

    $variant = ProductVariant::where('sku', '8680161820017')->first();
    expect($variant)->not->toBeNull()
        ->and($variant->barcode)->toBe('8680161820017')
        ->and((float) $variant->vat_rate)->toBe(18.00);

    $listing = ChannelListing::where('variant_id', $variant->id)->first();
    expect($listing)->not->toBeNull()
        ->and($listing->connection_id)->toBe($connection->id)
        ->and($listing->remote_id)->toBe('HBV00000U2NIV')
        ->and($listing->sync_state)->toBe(ListingSyncState::Synced);

    $run = SyncRun::where('resource', 'products')->first();
    expect($run)->not->toBeNull()
        ->and($run->status)->toBe(ProcessingStatus::Completed)
        ->and($run->stats['created'])->toBe(2);
});

it('matches existing variant by sku or barcode without creating duplicates', function (): void {
    $connection = hbConnection();

    $existingProduct = Product::factory()->create(['name' => 'Existing Watch']);
    $existingVariant = ProductVariant::factory()->for($existingProduct)->create([
        'sku' => '8680161820017',
        'barcode' => '8680161820017',
    ]);

    $stats = app(ImportProducts::class)->handle($connection, maxPages: 1);

    expect($stats['created'])->toBe(1)
        ->and($stats['matched'])->toBe(1)
        ->and(Product::count())->toBe(2) // 1 existing + 1 new
        ->and(ProductVariant::count())->toBe(2);

    $listing = ChannelListing::where('variant_id', $existingVariant->id)->first();
    expect($listing)->not->toBeNull()
        ->and($listing->connection_id)->toBe($connection->id)
        ->and($listing->remote_id)->toBe('HBV00000U2NIV')
        ->and($listing->sync_state)->toBe(ListingSyncState::Synced);
});

it('allows authorized users to pull products via controller endpoint', function (): void {
    $connection = hbConnection();
    $user = User::factory()->create()->assignRole('Yönetici');

    $this->actingAs($user)
        ->post(route('products.pull'), [
            'connection_id' => $connection->id,
        ])
        ->assertRedirect(route('products.index'));

    expect(Product::count())->toBe(2);
});

it('forbids unauthorized users from pulling products', function (): void {
    $connection = hbConnection();
    $user = User::factory()->create()->assignRole('Depo');

    $this->actingAs($user)
        ->post(route('products.pull'), [
            'connection_id' => $connection->id,
        ])
        ->assertForbidden();

    expect(Product::count())->toBe(0);
});
