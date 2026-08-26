<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
});

it('creates a tag and generates slug automatically', function (): void {
    $this->actingAs($this->manager)
        ->post(route('tags.store'), ['name' => 'Yeni Sezon Ürünleri'])
        ->assertRedirect();

    expect(Tag::query()->value('slug'))->toBe('yeni-sezon-urunleri');
});

it('refuses duplicate tag slug', function (): void {
    Tag::factory()->create(['name' => 'Outlet', 'slug' => 'outlet']);

    $this->actingAs($this->manager)
        ->post(route('tags.store'), ['name' => 'outlet'])
        ->assertSessionHasErrors('slug');

    expect(Tag::query()->count())->toBe(1);
});

it('lists tags with their product counts', function (): void {
    $tag = Tag::factory()->create(['name' => 'Trend']);
    $product = Product::factory()->create();
    $product->tags()->attach($tag);

    $res = $this->actingAs($this->manager)
        ->get(route('tags.index'));

    $res->assertOk();
    $res->assertInertia(fn (AssertableInertia $page) => $page
        ->component('catalog/tags/index')
        ->where('tags.0.name', 'Trend')
        ->where('tags.0.productCount', 1)
    );
});

it('deletes tag without deleting products', function (): void {
    $tag = Tag::factory()->create();
    $product = Product::factory()->create();
    $product->tags()->attach($tag);

    $this->actingAs($this->manager)
        ->delete(route('tags.destroy', $tag))
        ->assertRedirect();

    expect(Tag::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(1)
        ->and($product->refresh()->tags)->toHaveCount(0);
});
