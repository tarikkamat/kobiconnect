<?php

declare(strict_types=1);

use App\Actions\Mapping\SuggestMapping;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChannelAttributeMapping;
use App\Models\ChannelBrandMapping;
use App\Models\ChannelCategoryMapping;
use App\Models\ChannelConnection;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\Trendyol\Fixture;

/**
 * Onizleme adimi — BACKEND-PLAN §7.5. Kullanici gonderimden dort saat sonra
 * "reddedildi" gormemeli; eksikler burada, gonderimden once sayilir.
 */
beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
    $this->grantActiveLicense();

    $this->manager = User::factory()->create()->assignRole('Yönetici');

    $this->connection = ChannelConnection::factory()->create([
        'credentials' => ['seller_id' => '4321', 'api_key' => 'key', 'api_secret' => 'secret'],
    ]);

    $this->category = Category::factory()->create(['name' => 'Atkı']);
    $this->category->update(['path' => (string) $this->category->getKey()]);

    Http::fake([
        '*product/product-categories*' => Http::response(Fixture::json('category-tree')),
        '*/values*' => Http::response(Fixture::json('category-attribute-values')),
        '*/attributes' => Http::response(Fixture::json('category-attributes')),
        '*' => Http::response([]),
    ]);
});

/**
 * Onizleme adiminin urettigi maddeler.
 *
 * @return list<string>
 */
function mappingIssues(): array
{
    $response = test()->actingAs(test()->manager)->get(route('mapping.show', [
        'connection' => test()->connection,
        'category' => test()->category,
    ]));

    $response->assertOk();

    return array_column($response->viewData('page')['props']['issues'], 'message');
}

it('says the category is not mapped at all before anything is done', function (): void {
    expect(mappingIssues())->toContain('Kategori henüz bir pazaryeri kategorisine eşlenmedi.');
});

it('names every required attribute that is still unmapped', function (): void {
    ChannelCategoryMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'category_id' => $this->category->getKey(),
        'remote_category_id' => '382',
    ]);

    // Fixture'da 293 "Beden" required ve varianter.
    expect(mappingIssues())
        ->toContain('"Beden" özelliği bu kategoride zorunlu ama eşlenmemiş.')
        ->toContain('Bu kategoride varyant belirleyici bir özellik eşlenmemiş; varyantlar tek listelemeye katlanır.');
});

it('counts the values of an attribute that refuses free text', function (): void {
    ChannelCategoryMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'category_id' => $this->category->getKey(),
        'remote_category_id' => '382',
    ]);

    $size = Attribute::factory()->create(['name' => 'Beden']);
    AttributeValue::factory()->count(2)->create(['attribute_id' => $size->getKey()]);

    ChannelAttributeMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'remote_category_id' => '382',
        'attribute_id' => $size->getKey(),
        'remote_attribute_id' => '293',
        'is_required' => true,
        'allow_custom' => false,
        'is_varianter' => true,
    ]);

    expect(mappingIssues())
        ->toContain('"Beden" özelliği serbest metin kabul etmiyor; 2 değeriniz pazaryeri değerine eşlenmemiş.');
});

it('lists the brands of this category that have no marketplace counterpart', function (): void {
    ChannelCategoryMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'category_id' => $this->category->getKey(),
        'remote_category_id' => '382',
    ]);

    $mapped = Brand::factory()->create(['name' => 'Eşlenmiş']);
    $missing = Brand::factory()->create(['name' => 'Eksik']);

    foreach ([$mapped, $missing] as $brand) {
        Product::factory()->create([
            'category_id' => $this->category->getKey(),
            'brand_id' => $brand->getKey(),
        ]);
    }

    ChannelBrandMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'brand_id' => $mapped->getKey(),
        'remote_brand_id' => '40',
    ]);

    expect(mappingIssues())
        ->toContain('Bu kategorideki ürünlerin markaları pazaryeri markasına eşlenmemiş: Eksik.');
});

it('warns that a slicer attribute opens its own product card', function (): void {
    ChannelCategoryMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'category_id' => $this->category->getKey(),
        'remote_category_id' => '382',
    ]);

    $colour = Attribute::factory()->create(['name' => 'Renk']);

    ChannelAttributeMapping::query()->create([
        'connection_id' => $this->connection->getKey(),
        'remote_category_id' => '382',
        'attribute_id' => $colour->getKey(),
        'remote_attribute_id' => '294',
        'allow_custom' => true,
        'is_slicer' => true,
    ]);

    $issues = mappingIssues();

    expect(implode(' ', $issues))->toContain('ayrı ürün kartı açar');
});

it('compares names the way Turkish spelling requires, without any scoring model', function (): void {
    // Noktali/noktasiz I ve Turkce harfler karsilastirmadan once
    // transliterate edilir; "Kadın Elbise" ile "KADIN ELBİSE" ayni seydir.
    expect(SuggestMapping::normalize('Kadın Elbise'))
        ->toBe(SuggestMapping::normalize('KADIN ELBİSE'))
        ->and(SuggestMapping::normalize('Cep-Telefonu'))
        ->toBe(SuggestMapping::normalize('Cep Telefonu'));

    $suggest = new SuggestMapping;

    expect($suggest->best('Atkı', [7 => 'ATKI', 9 => 'Bere']))->toBe(7)
        ->and($suggest->best('Atkı', [9 => 'Buzdolabı']))->toBeNull();
});
