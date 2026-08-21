<?php

declare(strict_types=1);

use App\Models\InventoryItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->tenant = Tenant::create(['id' => 'test'.Str::lower(Str::random(10))]);
    tenancy()->initialize($this->tenant);
});

afterEach(function (): void {
    tenancy()->end();
    $this->tenant->delete();
});

it('computes inventory availability as a stored generated column', function (): void {
    $item = InventoryItem::factory()->create(['on_hand' => 10, 'reserved' => 3]);

    expect($item->refresh()->available)->toBe(7);

    $item->update(['reserved' => 8]);

    expect($item->refresh()->available)->toBe(2);
});

it('keeps one inventory row per variant and warehouse', function (): void {
    $item = InventoryItem::factory()->create();

    expect(fn () => InventoryItem::factory()->create([
        'variant_id' => $item->variant_id,
        'warehouse_id' => $item->warehouse_id,
    ]))->toThrow(QueryException::class);
});

it('matches turkish product names regardless of diacritics', function (): void {
    $match = Product::factory()->create(['name' => 'Şarj Aleti', 'description' => 'Hızlı şarj cihazı']);
    Product::factory()->create(['name' => 'Klavye', 'description' => 'Mekanik klavye']);

    expect(Product::search('sarj')->pluck('id')->all())->toBe([$match->id])
        ->and(Product::search('şarj')->pluck('id')->all())->toBe([$match->id])
        ->and(Product::search('klavye')->pluck('id')->all())->not->toContain($match->id);
});

it('ranks a name match above a description-only match', function (): void {
    $inName = Product::factory()->create(['name' => 'Termos', 'description' => 'Paslanmaz çelik']);
    $inDescription = Product::factory()->create(['name' => 'Matara', 'description' => 'Termos gibi tutar']);

    $ranked = Product::search('termos')
        ->orderByRaw("ts_rank(search_vector, websearch_to_tsquery('turkish', f_unaccent(?))) desc", ['termos'])
        ->pluck('id')->all();

    expect($ranked)->toBe([$inName->id, $inDescription->id]);
});

it('assigns a ulid alongside the auto incrementing key', function (): void {
    $product = Product::factory()->create();

    expect($product->id)->toBeInt()
        ->and($product->ulid)->toHaveLength(26);
});

it('rejects duplicate skus', function (): void {
    $variant = ProductVariant::factory()->create();

    expect(fn () => ProductVariant::factory()->create(['sku' => $variant->sku]))
        ->toThrow(QueryException::class);
});

it('cascades variants and inventory when a product is deleted', function (): void {
    $variant = ProductVariant::factory()->create();
    InventoryItem::factory()->create([
        'variant_id' => $variant->id,
        'warehouse_id' => Warehouse::factory(),
    ]);

    $variant->product->delete();

    expect(ProductVariant::count())->toBe(0)
        ->and(InventoryItem::count())->toBe(0);
});
