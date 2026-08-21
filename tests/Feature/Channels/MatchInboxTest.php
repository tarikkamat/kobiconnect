<?php

declare(strict_types=1);

use App\Marketplaces\Data\Enums\CanonicalListingStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    // Listeleme yaratmak outbox tetikleyicisini calistirir; bu ekranin testi
    // push kuyrugunu degil KARARI olcer.
    Queue::fake();

    $this->seed(TenantRoleSeeder::class);
    $this->grantActiveLicense();

    $this->manager = User::factory()->create()->assignRole('Yönetici');
    // 'Depo' katalogu GORUR ama yonetemez: karar butonlarinin neden kilitli
    // oldugunu dogrulayan taraf.
    $this->warehouseman = User::factory()->create()->assignRole('Depo');

    // On eslesme yalnizca SupportsCatalogMatching implement eden surucude var.
    $this->connection = ChannelConnection::factory()->create([
        'marketplace' => 'hepsiburada',
        'name' => 'Hepsiburada Ana',
        'credentials' => [
            'merchant_id' => 'c5779c28-af0a-43e1-a8a6-8b30782e79ec',
            'service_key' => 'test-secret',
            'integrator_user_agent' => 'kobiconnect_dev',
            'sit' => true,
        ],
    ]);
});

/**
 * Pazaryerinin bekleyen oneri kuyrugu. Sekil `ProductMapper::toMatchProposal`
 * ile birebir: oneri `matchedHbProductInfo` altinda gelir.
 */
function fakePrematchQueue(string $merchantSku, bool $last = true): void
{
    Http::fake([
        '*products-by-merchant-and-status*' => Http::response([
            'data' => [[
                'merchantSku' => $merchantSku,
                'matchedHbProductInfo' => [
                    'hbSku' => 'HBV000001',
                    'productName' => 'Pazaryeri Kırmızı Tişört',
                    'brand' => 'HB Marka',
                    'categoryName' => 'Tişört',
                    'images' => ['https://example.test/hb-1.jpg'],
                    'baseAttributes' => [['name' => 'Renk', 'value' => 'Kırmızı']],
                ],
            ]],
            'last' => $last,
        ]),
        '*prematch*' => Http::response(['trackingId' => 'trk-1']),
    ]);
}

function ourVariant(string $sku): ProductVariant
{
    $product = Product::factory()->create([
        'name' => 'Bizim Kırmızı Tişört',
        'brand_id' => Brand::factory()->create(['name' => 'Bizim Marka']),
        'category_id' => Category::factory()->create(['name' => 'Üst Giyim']),
    ]);

    ProductImage::factory()->create(['product_id' => $product->getKey(), 'position' => 0]);

    return ProductVariant::factory()->for($product)->create(['sku' => $sku]);
}

it('kendi kaydimizi ve onerilen kaydi YAN YANA verir', function (): void {
    fakePrematchQueue('ABC-1');
    $variant = ourVariant('abc-1');

    $this->actingAs($this->manager)
        ->get(route('matches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('channels/matching/index')
            ->where('connectionId', $this->connection->getKey())
            ->has('proposals', 1)
            ->where('proposals.0.reference', 'ABC-1')
            // Pazaryeri merchantSku'yu buyuk harfe cevirir; eslesme normalize
            // hali uzerinden kurulmazsa kendi urunumuzu bulamayiz.
            ->where('proposals.0.ours.variantId', $variant->getKey())
            ->where('proposals.0.ours.name', 'Bizim Kırmızı Tişört')
            ->where('proposals.0.ours.brand', 'Bizim Marka')
            ->where('proposals.0.ours.category', 'Üst Giyim')
            ->has('proposals.0.ours.images', 1)
            ->where('proposals.0.proposed.remoteId', 'HBV000001')
            ->where('proposals.0.proposed.name', 'Pazaryeri Kırmızı Tişört')
            ->where('proposals.0.proposed.brand', 'HB Marka')
            ->where('proposals.0.proposed.category', 'Tişört')
            ->has('proposals.0.proposed.images', 1)
            ->has('proposals.0.proposed.attributes', 1)
            ->where('error', null)
        );
});

it('karari verilmis oneriyi kuyrukta tekrar sormaz', function (): void {
    fakePrematchQueue('ABC-1');
    $variant = ourVariant('ABC-1');

    ChannelListing::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'variant_id' => $variant->getKey(),
        'remote_status' => CanonicalListingStatus::PendingApproval->value,
    ]);

    $this->actingAs($this->manager)
        ->get(route('matches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('proposals', 0));
});

it('karar bekleyen satir hala kuyrukta gorunur', function (): void {
    fakePrematchQueue('ABC-1');
    $variant = ourVariant('ABC-1');

    ChannelListing::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'variant_id' => $variant->getKey(),
        'remote_status' => CanonicalListingStatus::AwaitingMatchDecision->value,
    ]);

    $this->actingAs($this->manager)
        ->get(route('matches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('proposals', 1));
});

it('onay yalnizca ACIKCA gonderilen referanslara uygulanir', function (): void {
    fakePrematchQueue('ABC-1');
    $chosen = ourVariant('ABC-1');
    $untouched = ourVariant('XYZ-9');

    foreach ([$chosen, $untouched] as $variant) {
        ChannelListing::factory()->create([
            'connection_id' => $this->connection->getKey(),
            'variant_id' => $variant->getKey(),
            'remote_status' => CanonicalListingStatus::AwaitingMatchDecision->value,
        ]);
    }

    $this->actingAs($this->manager)
        ->post(route('matches.approve', ['connection' => $this->connection]), [
            'references' => ['ABC-1'],
        ])
        ->assertRedirect();

    expect(ChannelListing::query()->where('variant_id', $chosen->getKey())->value('remote_status'))
        ->toBe(CanonicalListingStatus::PendingApproval->value)
        // Otomatik onay yok: gonderilmeyen oneri bekliyor kalir.
        ->and(ChannelListing::query()->where('variant_id', $untouched->getKey())->value('remote_status'))
        ->toBe(CanonicalListingStatus::AwaitingMatchDecision->value);
});

it('red karari, reddin BIZDEN geldigini kaydeder', function (): void {
    fakePrematchQueue('ABC-1');
    $variant = ourVariant('ABC-1');

    ChannelListing::factory()->create([
        'connection_id' => $this->connection->getKey(),
        'variant_id' => $variant->getKey(),
        'remote_status' => CanonicalListingStatus::AwaitingMatchDecision->value,
    ]);

    $this->actingAs($this->manager)
        ->post(route('matches.reject', ['connection' => $this->connection]), [
            'references' => ['ABC-1'],
        ])
        ->assertRedirect();

    $listing = ChannelListing::query()->where('variant_id', $variant->getKey())->firstOrFail();

    expect($listing->remote_status)->toBe(CanonicalListingStatus::Rejected->value)
        ->and($listing->error['code'] ?? null)->toBe('match_rejected');
});

it('katalogu yonetemeyen kullanici karar veremez', function (): void {
    fakePrematchQueue('ABC-1');
    ourVariant('ABC-1');

    $this->actingAs($this->warehouseman)
        ->post(route('matches.approve', ['connection' => $this->connection]), [
            'references' => ['ABC-1'],
        ])
        ->assertForbidden();
});

it('kuyruk okunamadiginda ekran 500 vermez, sebebi ust bantta yazar', function (): void {
    Http::fake(['*' => Http::response(['message' => 'bozuk'], 500)]);

    $this->actingAs($this->manager)
        ->get(route('matches.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('proposals', 0)
            ->whereNot('error', null)
        );
});
