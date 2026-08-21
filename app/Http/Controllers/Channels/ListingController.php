<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Enums\ListingSyncState;
use App\Http\Controllers\Controller;
use App\Marketplaces\Data\Enums\CanonicalListingStatus;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ChannelOperation;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Varyant × kanal matrisi: `channel_listings` defterinin ekrani.
 *
 * Tablo bugune kadar dolu ama gorunmezdi; bir varyantin bir kanalda hangi
 * durumda oldugunu gormenin baska yolu yoktu.
 *
 * Ekranin varlik sebebi tek bir satirda ozetlenebilir: **`degraded`**. Fiyat
 * bandi ihlalinde pazaryeri urunu REDDETMEZ, 0 fiyat / 0 stok ile CANLIYA
 * cikarir (HEPSIBURADA.md §3 H6, olculdu). Push "basarili", defter
 * "tamamlandi", satici yesil gorur ve hicbir sey satilmaz. Bu yuzden bozulmus
 * kabul burada AYRI bir rozet, AYRI bir sayac ve ust bantta bir uyari olarak
 * durur; "tamamlandi" satirlarinin arasinda kaybolamaz.
 */
class ListingController extends Controller
{
    /**
     * Arayuz metinleri Turkce, kanonik enum degerleri degil — FRONTEND-PLAN §7.
     *
     * @var array<string, string>
     */
    private const array REMOTE_STATUS_LABELS = [
        'pending_approval' => 'Pazaryeri inceliyor',
        'awaiting_match_decision' => 'Kararınız bekleniyor',
        'rejected' => 'Reddedildi',
        'on_sale' => 'Satışta',
        'not_on_sale' => 'Satışta değil',
        'archived' => 'Arşivlendi',
        'blacklisted' => 'Kara listede',
        'locked' => 'Kilitli',
    ];

    /**
     * @var array<string, string>
     */
    private const array SYNC_STATE_LABELS = [
        'pending' => 'Beklemede',
        'syncing' => 'Gönderiliyor',
        'synced' => 'Gönderildi',
        'failed' => 'Başarısız',
    ];

    /**
     * Bozulmus kabulun tek SQL ifadesi. Rozet de, "yalnız sorunlular" filtresi
     * de bunu okur — iki ayri kural iki ayri gercek uretirdi.
     */
    private const string DEGRADED = "coalesce((op.remote_result->>'degraded')::boolean, false)";

    public function index(Request $request): Response
    {
        Gate::authorize('catalog.view');

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(CanonicalListingStatus::class)],
            'connection' => ['nullable', 'integer'],
            'problem' => ['nullable', 'boolean'],
        ]);

        $status = $filters['status'] ?? null;
        $connection = isset($filters['connection']) ? (int) $filters['connection'] : null;
        $problem = (bool) ($filters['problem'] ?? false);

        $listings = $this->query()
            ->when($status !== null, fn (Builder $query) => $query->where('remote_status', $status))
            ->when($connection !== null, fn (Builder $query) => $query->where('channel_listings.connection_id', $connection))
            ->when($problem, fn (Builder $query) => $query->where($this->problems(...)))
            ->orderByDesc('channel_listings.id')
            ->paginate(30)
            ->withQueryString()
            ->through($this->row(...));

        return Inertia::render('channels/listings/index', [
            // Inertia::scroll() birlestirme davranisini <InfiniteScroll> icin
            // normalize eder — sayfalama tiklamasi yok.
            'listings' => Inertia::scroll($listings),
            'filters' => ['status' => $status, 'connection' => $connection, 'problem' => $problem],
            'statuses' => $this->statusOptions(),
            'connections' => ChannelConnection::query()->orderBy('name')->get(['id', 'name']),
            // Sessiz felaketin sayaci: filtreden BAGIMSIZ, cunku bu sayinin
            // sifirdan farkli olmasi her zaman haber degeri tasir.
            'degradedCount' => $this->query()->whereRaw(self::DEGRADED)->count(),
        ]);
    }

    /**
     * Listeleme + o listelemenin SON tamamlanmis islemi.
     *
     * `distinct on` ile tek satira indirilir: bozulmus kabul yapiskan bir
     * damga degildir, sonraki basarili push onu temizler. "Herhangi bir zaman
     * bozulmustu" demek satiri sonsuza kadar kirmizi birakirdi.
     *
     * @return Builder<ChannelListing>
     */
    private function query(): Builder
    {
        $latest = ChannelOperation::query()
            ->selectRaw('distinct on (connection_id, entity_id) connection_id, entity_id, remote_result')
            ->where('entity_type', ProductVariant::class)
            ->where('status', SyncState::Completed)
            ->orderByRaw('connection_id, entity_id, completed_at desc');

        return ChannelListing::query()
            ->select('channel_listings.*')
            ->selectRaw(self::DEGRADED.' as degraded')
            ->selectRaw("op.remote_result->>'message' as degraded_message")
            ->leftJoinSub($latest, 'op', fn (JoinClause $join) => $join
                ->on('op.connection_id', '=', 'channel_listings.connection_id')
                ->on('op.entity_id', '=', 'channel_listings.variant_id'))
            ->with(['connection:id,name', 'variant:id,product_id,sku', 'variant.product:id,name']);
    }

    /**
     * "Yalnizca sorunlular": satici bugun bir sey yapmasi gereken satirlar.
     *
     * @param  Builder<ChannelListing>  $query
     */
    private function problems(Builder $query): void
    {
        $query
            ->where('sync_state', ListingSyncState::Failed)
            ->orWhereNotNull('channel_listings.error')
            ->orWhereIn('remote_status', [
                CanonicalListingStatus::AwaitingMatchDecision->value,
                CanonicalListingStatus::Rejected->value,
            ])
            ->orWhereRaw(self::DEGRADED);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ChannelListing $listing): array
    {
        $remoteStatus = $listing->remote_status;

        return [
            'id' => $listing->getKey(),
            'connectionId' => $listing->connection_id,
            'connection' => $listing->connection->name,
            'productId' => $listing->variant->product_id,
            'product' => $listing->variant->product->name,
            'sku' => $listing->variant->sku,
            'remoteId' => $listing->remote_id,
            'syncState' => $listing->sync_state->value,
            'syncStateLabel' => self::SYNC_STATE_LABELS[$listing->sync_state->value],
            'remoteStatus' => $remoteStatus,
            'remoteStatusLabel' => $remoteStatus === null
                ? 'Gönderilmedi'
                : (self::REMOTE_STATUS_LABELS[$remoteStatus] ?? $remoteStatus),
            // Tarih sunucuda bicimlenir, Europe/Istanbul — FRONTEND-PLAN §7.
            'lastPushedAt' => $listing->last_pushed_at?->timezone('Europe/Istanbul')->format('d.m.Y H:i'),
            'degraded' => (bool) $listing->getAttribute('degraded'),
            'degradedMessage' => $this->stringOrNull($listing->getAttribute('degraded_message')),
            'error' => $this->stringOrNull($listing->error['message'] ?? null),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            static fn (CanonicalListingStatus $status): array => [
                'value' => $status->value,
                // Yedek yok: sozluk enum'un HER case'ini karsilamak zorunda,
                // eksik birakilan bir case burada patlasin.
                'label' => self::REMOTE_STATUS_LABELS[$status->value],
            ],
            CanonicalListingStatus::cases(),
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
