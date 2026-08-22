<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChannelAttributeMapping;
use App\Models\ChannelAttributeValueMapping;
use App\Models\ChannelBrandMapping;
use App\Models\ChannelCategoryMapping;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Fixtures\Trendyol\Fixture;

/**
 * Referans veri uclarinin hepsi tek yerden taklit edilir; govdeler
 * TRENDYOL.md'den birebir kopyalanmis fixture'lardir.
 *
 * Agaca ikinci bir yaprak ("Bere", 383) eklenir: fixture tek yaprak tasiyor ve
 * "eslemeyi baska bir kategoriye tasi" yolu tek yaprakla test edilemez. Sekil
 * fixture'in sekli, uydurulan tek sey ikinci dugum.
 *
 * @param  array<string, mixed>|null  $attributes
 */
function fakeTrendyolCatalog(?array $attributes = null): void
{
    $tree = Fixture::json('category-tree');
    $tree[0]['subCategories'][] = ['id' => 383, 'name' => 'Bere', 'parentId' => 1162, 'subCategories' => []];

    Http::fake([
        '*product/product-categories*' => Http::response($tree),
        '*/values*' => Http::response(Fixture::json('category-attribute-values')),
        '*/attributes' => Http::response($attributes ?? Fixture::json('category-attributes')),
        '*brands/by-name*' => Http::response(Fixture::json('brands-by-name')),
        '*' => Http::response([]),
    ]);
}

/**
 * Kismi yeniden yukleme (Inertia::optional prop'lari) icin gereken basliklar.
 *
 * @return array<string, string>
 */
function partialHeaders(string $prop): array
{
    // Surum basligi eksik ya da farkli olursa Inertia 409 doner; degeri
    // uygulamanin kendi middleware'inden okuyoruz ki manifest degistiginde
    // test kirilmasin.
    return [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'channels/mapping/wizard',
        'X-Inertia-Partial-Data' => $prop,
    ];
}

function mappingCategory(string $name = 'Atkı'): Category
{
    $category = Category::factory()->create(['name' => $name]);
    $category->update(['path' => (string) $category->getKey()]);

    return $category->refresh();
}

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');

    // TrendyolCredentials sayisal bir satici id sart kosar; factory varsayilani
    // bunu tasimiyor.
    $this->connection = ChannelConnection::factory()->create([
        'name' => 'Ana mağaza',
        'credentials' => ['seller_id' => '4321', 'api_key' => 'key', 'api_secret' => 'secret'],
    ]);

    $this->category = mappingCategory();

    fakeTrendyolCatalog();
});

it('lists our own categories with a database derived mapping status', function (): void {
    $this->actingAs($this->manager)
        ->get(route('mapping.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('channels/mapping/index')
            ->where('connectionId', $this->connection->getKey())
            ->where('categories.0.status', 'unmapped')
            ->where('categories.0.name', 'Atkı')
        );
});

it('suggests the marketplace leaf whose name matches ours', function (): void {
    $this->actingAs($this->manager)
        ->get(route('mapping.show', ['connection' => $this->connection, 'category' => $this->category]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('channels/mapping/wizard')
            ->where('mapping', null)
            ->where('suggestions.0.remoteId', '382')
            ->where('suggestions.0.score', 100)
            ->where('lock.locked', false)
        );
});

it('refuses a category that still has children, because the marketplace takes no products there', function (): void {
    // 1162 "Atkı & Bere & Eldiven" fixture agacinda bir UST kategori.
    $this->actingAs($this->manager)
        ->post(
            route('mapping.category', ['connection' => $this->connection, 'category' => $this->category]),
            ['remote_category_id' => '1162'],
        )
        ->assertSessionHasErrors('remote_category_id');

    expect(ChannelCategoryMapping::query()->count())->toBe(0);

    expect(session('errors')->first('remote_category_id'))
        ->toContain('üst kategori');
});

it('stores a leaf mapping with its human readable path', function (): void {
    $this->actingAs($this->manager)
        ->post(
            route('mapping.category', ['connection' => $this->connection, 'category' => $this->category]),
            ['remote_category_id' => '382'],
        )
        ->assertRedirect();

    $mapping = ChannelCategoryMapping::query()->sole();

    expect($mapping->remote_category_id)->toBe('382')
        ->and($mapping->remote_path)->toBe('Atkı & Bere & Eldiven > Atkı');
});

it('copies the attribute flags from the marketplace and ignores anything the client sends', function (): void {
    ChannelCategoryMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'category_id' => $this->category->getKey(),
        'remote_category_id' => '382',
    ]);

    $size = Attribute::factory()->create(['name' => 'Beden']);

    $this->actingAs($this->manager)
        ->post(
            route('mapping.attributes', ['connection' => $this->connection, 'category' => $this->category]),
            ['attributes' => [[
                'remote_attribute_id' => '293',
                'attribute_id' => $size->getKey(),
                // Istemci bayrak uydurursa yok sayilmali: bunlar pazaryerinin
                // gercegidir ve yerel on-dogrulama bunlarin uzerine kurulu.
                'is_required' => false,
                'is_varianter' => false,
            ]]],
        )
        ->assertRedirect();

    $mapping = ChannelAttributeMapping::query()->sole();

    expect($mapping->attribute_id)->toBe($size->getKey())
        ->and($mapping->is_required)->toBeTrue()
        ->and($mapping->is_varianter)->toBeTrue()
        ->and($mapping->allow_custom)->toBeFalse()
        ->and($mapping->allow_multiple)->toBeFalse()
        ->and($mapping->is_slicer)->toBeFalse();
});

it('refuses a second varianter, because the marketplace allows exactly one per category', function (): void {
    $payload = Fixture::json('category-attributes');
    $payload['categoryAttributes'][1] = [
        'allowCustom' => false,
        'attribute' => ['id' => 294, 'name' => 'Renk'],
        'categoryId' => 14609,
        'required' => false,
        'varianter' => true,
        'slicer' => false,
        'allowMultipleAttributeValues' => false,
    ];

    fakeTrendyolCatalog($payload);

    ChannelCategoryMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'category_id' => $this->category->getKey(),
        'remote_category_id' => '382',
    ]);

    $size = Attribute::factory()->create(['name' => 'Beden']);
    $colour = Attribute::factory()->create(['name' => 'Renk']);

    $this->actingAs($this->manager)
        ->post(
            route('mapping.attributes', ['connection' => $this->connection, 'category' => $this->category]),
            ['attributes' => [
                ['remote_attribute_id' => '293', 'attribute_id' => $size->getKey()],
                ['remote_attribute_id' => '294', 'attribute_id' => $colour->getKey()],
            ]],
        )
        ->assertSessionHasErrors('attributes');

    expect(ChannelAttributeMapping::query()->count())->toBe(0);
});

it('locks varianter and slicer once an approved listing exists', function (): void {
    ChannelCategoryMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'category_id' => $this->category->getKey(),
        'remote_category_id' => '382',
    ]);

    $size = Attribute::factory()->create(['name' => 'Beden']);
    $other = Attribute::factory()->create(['name' => 'Kalıp']);

    ChannelAttributeMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'remote_category_id' => '382',
        'attribute_id' => $size->getKey(),
        'remote_attribute_id' => '293',
        'is_required' => true,
        'is_varianter' => true,
    ]);

    // Onay bir DURUMDUR, bir endpoint degil (TRENDYOL.md §9.3).
    $product = Product::factory()->create(['category_id' => $this->category->getKey()]);
    ChannelListing::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'variant_id' => ProductVariant::factory()->create(['product_id' => $product->getKey()])->getKey(),
        'remote_status' => 'approved',
    ]);

    $this->actingAs($this->manager)
        ->post(
            route('mapping.attributes', ['connection' => $this->connection, 'category' => $this->category]),
            ['attributes' => [['remote_attribute_id' => '293', 'attribute_id' => $other->getKey()]]],
        )
        ->assertSessionHasErrors('attributes');

    expect(ChannelAttributeMapping::query()->sole()->attribute_id)->toBe($size->getKey());

    // Kategori de sabitlenir: onayli urunde `categoryId` degismez.
    $this->actingAs($this->manager)
        ->post(
            route('mapping.category', ['connection' => $this->connection, 'category' => $this->category]),
            ['remote_category_id' => '1162'],
        )
        ->assertSessionHasErrors('remote_category_id');

    $this->actingAs($this->manager)
        ->get(route('mapping.show', ['connection' => $this->connection, 'category' => $this->category]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('lock.locked', true)
            ->where('lock.reason', fn (?string $reason): bool => is_string($reason) && $reason !== '')
        );
});

it('pre-marks value mappings that match by name and stores what the user confirms', function (): void {
    ChannelCategoryMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'category_id' => $this->category->getKey(),
        'remote_category_id' => '382',
    ]);

    $size = Attribute::factory()->create(['name' => 'Beden']);
    $value = AttributeValue::factory()->create(['attribute_id' => $size->getKey(), 'value' => 'Tek ebat']);

    $mapping = ChannelAttributeMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'remote_category_id' => '382',
        'attribute_id' => $size->getKey(),
        'remote_attribute_id' => '293',
        'is_required' => true,
        'is_varianter' => true,
    ]);

    // "Tek ebat" ↔ "Tek Ebat": normalize edilmis isim esitligi, skorlama degil.
    $this->actingAs($this->manager)
        ->get(route('mapping.show', ['connection' => $this->connection, 'category' => $this->category]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where("attributes.0.suggestedValues.{$value->getKey()}", '4872')
        );

    $this->actingAs($this->manager)
        ->post(
            route('mapping.values', ['connection' => $this->connection, 'category' => $this->category]),
            ['values' => [[
                'mapping_id' => $mapping->getKey(),
                'attribute_value_id' => $value->getKey(),
                'remote_value_id' => '4872',
            ]]],
        )
        ->assertRedirect();

    expect(ChannelAttributeValueMapping::query()->sole()->remote_value_id)->toBe('4872');
});

it('finds a brand only on an exact, case sensitive name and explains a miss', function (): void {
    $brand = Brand::factory()->create(['name' => 'TRENDYOLMİLLA']);
    Product::factory()->create([
        'category_id' => $this->category->getKey(),
        'brand_id' => $brand->getKey(),
    ]);

    // Kismi yeniden yukleme JSON doner, tam sayfa degil.
    $this->actingAs($this->manager)
        ->get(route('mapping.show', [
            'connection' => $this->connection,
            'category' => $this->category,
            'brand' => 'TRENDYOLMİLLA',
        ]), partialHeaders('brandResult'))
        ->assertOk()
        ->assertJsonPath('props.brandResult.brand.remoteId', '40');

    // Ayni marka kucuk harfle aranirsa Trendyol'un birebir eslesmesi tutmaz.
    $this->actingAs($this->manager)
        ->get(route('mapping.show', [
            'connection' => $this->connection,
            'category' => $this->category,
            'brand' => 'Trendyolmilla',
        ]), partialHeaders('brandResult'))
        ->assertOk()
        ->assertJsonPath('props.brandResult.brand', null);

    $this->actingAs($this->manager)
        ->post(
            route('mapping.brands', ['connection' => $this->connection, 'category' => $this->category]),
            ['brands' => [['brand_id' => $brand->getKey(), 'remote_brand_id' => '40']]],
        )
        ->assertRedirect();

    expect(ChannelBrandMapping::query()->sole()->remote_brand_id)->toBe('40');
});

it('marks a non leaf search hit as unselectable instead of hiding it', function (): void {
    $this->actingAs($this->manager)
        ->get(route('mapping.show', [
            'connection' => $this->connection,
            'category' => $this->category,
            'q' => 'Atkı',
        ]), partialHeaders('searchResults'))
        ->assertOk()
        ->assertJsonPath('props.searchResults.0.isLeaf', false)
        ->assertJsonPath('props.searchResults.0.path', 'Atkı & Bere & Eldiven')
        ->assertJsonPath('props.searchResults.1.isLeaf', true);
});

it('drops the attribute mappings of a remote category nobody points at any more', function (): void {
    // `channel_attribute_mappings` yerel kategoriye degil UZAK kategoriye
    // bagli: yeniden esleme eskisini bosaltmali, ama yalnizca baska kimse ona
    // bakmiyorsa.
    $shared = mappingCategory('Bere');

    foreach ([$this->category, $shared] as $category) {
        ChannelCategoryMapping::query()->create([
            'connection_id' => $this->connection->getKey(),
            'category_id' => $category->getKey(),
            'remote_category_id' => '382',
        ]);
    }

    ChannelAttributeMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'remote_category_id' => '382',
        'attribute_id' => Attribute::factory()->create()->getKey(),
        'remote_attribute_id' => '293',
    ]);

    // "Bere" hâlâ 382'ye bakiyor: ozellik eslemeleri korunur.
    $this->actingAs($this->manager)->post(
        route('mapping.category', ['connection' => $this->connection, 'category' => $this->category]),
        ['remote_category_id' => '383'],
    )->assertSessionHasNoErrors();

    expect(ChannelAttributeMapping::query()->where('remote_category_id', '382')->count())->toBe(1);

    // Son bakan da ayrildiginda anlamini yitirir ve silinir.
    $this->actingAs($this->manager)->post(
        route('mapping.category', ['connection' => $this->connection, 'category' => $shared]),
        ['remote_category_id' => '383'],
    )->assertSessionHasNoErrors();

    expect(ChannelAttributeMapping::query()->where('remote_category_id', '382')->count())->toBe(0);
});

it('keeps the wizard out of reach without the channels permission', function (): void {
    $viewer = User::factory()->create()->assignRole('Depo');

    $this->actingAs($viewer)
        ->get(route('mapping.index'))
        ->assertForbidden();

    $this->actingAs($viewer)
        ->post(
            route('mapping.category', ['connection' => $this->connection, 'category' => $this->category]),
            ['remote_category_id' => '382'],
        )
        ->assertForbidden();
});
