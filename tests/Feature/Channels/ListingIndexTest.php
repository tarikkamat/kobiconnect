<?php

declare(strict_types=1);

use App\Enums\ListingSyncState;
use App\Marketplaces\Data\Enums\CanonicalListingStatus;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ChannelOperation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    // Listeleme yaratmak outbox tetikleyicisini calistirir; bu ekran salt
    // okunurdur, push kuyrugu testin konusu degil.
    Queue::fake();

    $this->seed(TenantRoleSeeder::class);

    $this->manager = User::factory()->create()->assignRole('Yönetici');
    $this->connection = ChannelConnection::factory()->create(['name' => 'Trendyol Ana']);
});

function listingFor(ChannelConnection $connection, array $attributes = []): ChannelListing
{
    $variant = ProductVariant::factory()->for(Product::factory())->create();

    return ChannelListing::factory()->create([
        'connection_id' => $connection->getKey(),
        'variant_id' => $variant->getKey(),
        ...$attributes,
    ]);
}

function completedOperation(ChannelListing $listing, ?array $remoteResult, int $minutesAgo = 5): ChannelOperation
{
    return ChannelOperation::factory()->create([
        'connection_id' => $listing->connection_id,
        'entity_type' => ProductVariant::class,
        'entity_id' => $listing->variant_id,
        'status' => SyncState::Completed,
        'remote_result' => $remoteResult,
        'completed_at' => now()->subMinutes($minutesAgo),
    ]);
}

it('bozulmus kabulu AYRI bir sinyal olarak gosterir', function (): void {
    $degraded = listingFor($this->connection, [
        'sync_state' => ListingSyncState::Synced,
        'remote_status' => CanonicalListingStatus::OnSale->value,
        'last_pushed_at' => now(),
    ]);

    completedOperation($degraded, [
        'degraded' => true,
        'message' => 'Fiyat bandı ihlali: listeleme 0 fiyat / 0 stok ile açıldı.',
    ]);

    listingFor($this->connection, [
        'sync_state' => ListingSyncState::Synced,
        'remote_status' => CanonicalListingStatus::OnSale->value,
    ]);

    $this->actingAs($this->manager)
        ->get(route('listings.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('channels/listings/index')
            ->has('listings.data', 2)
            // Sayac filtreden BAGIMSIZ: sifirdan farkli olmasi her zaman haberdir.
            ->where('degradedCount', 1)
            ->where('listings.data.1.degraded', true)
            ->where('listings.data.1.degradedMessage', 'Fiyat bandı ihlali: listeleme 0 fiyat / 0 stok ile açıldı.')
            // "Satışta" rozeti tek basina yalan soyler; bozulmus kabul onun
            // yaninda ayri bir alan olarak durmali.
            ->where('listings.data.1.remoteStatusLabel', 'Satışta')
            ->where('listings.data.0.degraded', false)
        );
});

it('sonraki temiz push bozulmus damgasini temizler', function (): void {
    $listing = listingFor($this->connection, ['sync_state' => ListingSyncState::Synced]);

    completedOperation($listing, ['degraded' => true, 'message' => 'eski'], minutesAgo: 30);
    completedOperation($listing, ['degraded' => false], minutesAgo: 1);

    $this->actingAs($this->manager)
        ->get(route('listings.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('listings.data.0.degraded', false)
            ->where('degradedCount', 0)
        );
});

it('yalniz sorunlular filtresi bozulmus ve karar bekleyen satirlari tutar', function (): void {
    $healthy = listingFor($this->connection, [
        'sync_state' => ListingSyncState::Synced,
        'remote_status' => CanonicalListingStatus::OnSale->value,
    ]);
    completedOperation($healthy, ['degraded' => false]);

    $awaiting = listingFor($this->connection, [
        'remote_status' => CanonicalListingStatus::AwaitingMatchDecision->value,
    ]);

    $degraded = listingFor($this->connection, ['sync_state' => ListingSyncState::Synced]);
    completedOperation($degraded, ['degraded' => true, 'message' => '0 fiyat']);

    $this->actingAs($this->manager)
        ->get(route('listings.index', ['problem' => 1]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('listings.data', 2)
            ->where('filters.problem', true)
            ->where('listings.data.0.id', $degraded->getKey())
            ->where('listings.data.1.id', $awaiting->getKey())
        );
});

it('durum sozlugu enum icindeki her case i karsilar', function (): void {
    $this->actingAs($this->manager)
        ->get(route('listings.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('statuses', count(CanonicalListingStatus::cases()))
        );
});

it('katalogu goremeyen kullanici listelemeleri de goremez', function (): void {
    $accountant = User::factory()->create();

    $this->actingAs($accountant)
        ->get(route('listings.index'))
        ->assertForbidden();
});
