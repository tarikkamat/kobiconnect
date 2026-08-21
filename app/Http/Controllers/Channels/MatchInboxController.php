<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Matching\DecideMatches;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\MatchDecisionRequest;
use App\Marketplaces\Contracts\SupportsCatalogMatching;
use App\Marketplaces\Data\AttributeValueData;
use App\Marketplaces\Data\Enums\CanonicalListingStatus;
use App\Marketplaces\Data\MatchProposalData;
use App\Marketplaces\Data\PullPage;
use App\Marketplaces\Support\Capability;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Support\Sync\ConnectionDriver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Ön eşleşme gelen kutusu — HEPSIBURADA.md §3 H10, §10 K1.
 *
 * Pazaryeri gonderdigimiz urunun kendi katalogundaki bir kayitla ayni oldugunu
 * DUSUNUYOR ve kararimizi bekliyor. Karar verilene kadar urun satisa ACILMAZ:
 * islenmemis bir kuyruk, "5000 SKU yukledim hicbiri satmiyor"un birinci sebebi.
 *
 * Bu bir durum alani degil, bir GELEN KUTUSUDUR — kendi yasam dongusu, kendi
 * satici aksiyonu ve kendi ekrani var.
 *
 * OTOMATIK ONAY YOK. Onay teknik degil TICARI bir karardir: onaylandiginda
 * pazaryerinin basligi, gorselleri ve attribute'lari devralinir; yanlis eslesme
 * A urununu B'nin sayfasinda satmak demektir. Bu yuzden ekran yan yana
 * karsilastirma gosterir ve her karar acikca tiklanir.
 *
 * Oneriler YEREL OLARAK SAKLANMAZ: tek dogru kaynak pazaryerinin kendi
 * kuyrugudur ve arada gecen surede degisebilir. Yerelde tutulan tek sey
 * VERILMIS KARARDIR (`channel_listings.remote_status`), ki karar sonrasi ayni
 * satir kuyrukta gorunmeye devam etse bile tekrar sorulmasin.
 */
class MatchInboxController extends Controller
{
    /**
     * Pazaryeri kuyrugu okunamadiginda ekran 500 vermez; sebep ust bantta yazar.
     */
    private ?string $inboxError = null;

    public function __construct(private readonly ConnectionDriver $drivers) {}

    public function index(Request $request): Response
    {
        Gate::authorize('catalog.view');

        $connections = $this->matchingConnections();
        $connection = $connections->firstWhere('id', $request->integer('connection')) ?? $connections->first();
        $cursor = $request->string('cursor')->toString();
        $page = $connection === null ? null : $this->proposals($connection, $cursor === '' ? null : $cursor);

        return Inertia::render('channels/matching/index', [
            'connections' => $connections
                ->map(fn (ChannelConnection $row): array => [
                    'id' => $row->getKey(),
                    'name' => $row->name,
                ])
                ->values()
                ->all(),
            'connectionId' => $connection?->getKey(),
            'proposals' => $connection === null || $page === null ? [] : $this->rows($connection, $page->items),
            // Kuyruk imlec tabanlidir; "daha fazla" bir sonraki sayfayi ayni
            // ekrana getirir. Sayfa numarasi yok — pazaryeri de vermiyor.
            'nextCursor' => $page?->hasMore === true ? $page->cursor : null,
            'error' => $this->inboxError,
        ]);
    }

    public function approve(MatchDecisionRequest $request, ChannelConnection $connection, DecideMatches $decide): RedirectResponse
    {
        return $this->decide($request, $connection, $decide, approve: true);
    }

    public function reject(MatchDecisionRequest $request, ChannelConnection $connection, DecideMatches $decide): RedirectResponse
    {
        return $this->decide($request, $connection, $decide, approve: false);
    }

    private function decide(
        MatchDecisionRequest $request,
        ChannelConnection $connection,
        DecideMatches $decide,
        bool $approve,
    ): RedirectResponse {
        Gate::authorize('catalog.manage');

        $result = $decide($connection, $request->references(), $approve);

        Inertia::flash('toast', [
            'type' => $result['ok'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }

    /**
     * Yalnizca surucusu ON ESLESME sunan baglantilar. Yetenek listesi
     * `capabilities` kolonundan degil surucunun implement ettigi arayuzden
     * turer — kolon saglik kontrolunde tazelenir ve eskimis olabilir.
     *
     * @return EloquentCollection<int, ChannelConnection>
     */
    private function matchingConnections(): EloquentCollection
    {
        return ChannelConnection::query()
            ->orderBy('name')
            ->get()
            ->filter(function (ChannelConnection $connection): bool {
                try {
                    return Capability::CatalogMatching->driverSupports($this->drivers->for($connection));
                } catch (Throwable) {
                    return false;
                }
            });
    }

    /**
     * @return PullPage<MatchProposalData>|null
     */
    private function proposals(ChannelConnection $connection, ?string $cursor): ?PullPage
    {
        $driver = $this->drivers->for($connection);

        if (! $driver instanceof SupportsCatalogMatching) {
            return null;
        }

        try {
            return $driver->pendingMatchProposals($cursor);
        } catch (Throwable $exception) {
            $this->inboxError = 'Pazaryerinin ön eşleşme kuyruğu okunamadı: '.$exception->getMessage()
                .' Verilmiş kararlarınız duruyor, birkaç dakika sonra tekrar deneyin.';

            return null;
        }
    }

    /**
     * Oneri satirlari: SOLDA bizim urunumuz, SAGDA pazaryerinin onerdigi kayit.
     *
     * Karari verilmis oneriler dusulur. Pazaryeri `approve-prematch` icin poll
     * ucu vermiyor (§3 H4), yani onay sonrasi ayni satiri bir sure daha
     * dondurebilir; yerel karar olmasa satici ayni urunu tekrar onaylardi.
     *
     * @param  list<MatchProposalData>  $proposals
     * @return list<array<string, mixed>>
     */
    private function rows(ChannelConnection $connection, array $proposals): array
    {
        $references = array_values(array_unique(
            array_map(static fn (MatchProposalData $proposal): string => $proposal->reference, $proposals),
        ));

        if ($references === []) {
            return [];
        }

        $variants = ProductVariant::query()
            ->with(['product:id,name,brand_id,category_id', 'product.brand:id,name', 'product.category:id,name'])
            ->whereIn(DB::raw("upper(replace(sku, ' ', ''))"), $references)
            ->get()
            ->keyBy(fn (ProductVariant $variant): string => $this->normalize($variant->sku));

        $images = ProductImage::query()
            ->whereIn('product_id', $variants->pluck('product_id')->filter()->unique())
            ->orderBy('position')
            ->get()
            ->groupBy('product_id');

        $decided = $this->decidedVariantIds($connection, $variants);
        $rows = [];

        foreach ($proposals as $proposal) {
            $variant = $variants->get($proposal->reference);

            if ($variant !== null && in_array((int) $variant->getKey(), $decided, true)) {
                continue;
            }

            $rows[] = [
                'reference' => $proposal->reference,
                'ours' => $variant === null ? null : [
                    'variantId' => (int) $variant->getKey(),
                    'productId' => (int) $variant->product_id,
                    'sku' => $variant->sku,
                    'name' => $variant->product?->name,
                    'brand' => $variant->product?->brand?->name,
                    'category' => $variant->product?->category?->name,
                    'images' => $images->get($variant->product_id, collect())
                        ->take(4)
                        ->map(static fn (ProductImage $image): string => $image->url)
                        ->values()
                        ->all(),
                    'attributes' => $this->ourAttributes($variant),
                ],
                'proposed' => [
                    'remoteId' => $proposal->proposedRemoteId,
                    'name' => $proposal->proposedName,
                    'brand' => $proposal->proposedBrand,
                    'category' => $proposal->proposedCategoryName,
                    'images' => array_slice($proposal->proposedImages, 0, 4),
                    'attributes' => array_map(
                        static fn (AttributeValueData $value): array => [
                            'name' => $value->attributeCode ?? '—',
                            'value' => $value->value,
                        ],
                        $proposal->proposedAttributes,
                    ),
                ],
            ];
        }

        return $rows;
    }

    /**
     * Karari verilmis (dolayisiyla gelen kutusunda gosterilmeyecek) varyantlar.
     *
     * `remote_status` null ise hicbir sey bilmiyoruz demektir, karar verilmis
     * sayilmaz; `awaiting_match_decision` ise hala bizi bekliyor.
     *
     * @param  EloquentCollection<string, ProductVariant>  $variants
     * @return list<int>
     */
    private function decidedVariantIds(ChannelConnection $connection, EloquentCollection $variants): array
    {
        /** @var list<int> $ids */
        $ids = ChannelListing::query()
            ->where('connection_id', $connection->getKey())
            ->whereIn('variant_id', $variants->modelKeys())
            ->whereNotNull('remote_status')
            ->where('remote_status', '!=', CanonicalListingStatus::AwaitingMatchDecision->value)
            ->pluck('variant_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $ids;
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function ourAttributes(ProductVariant $variant): array
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $variant->getAttribute('attributes') ?? [];
        $rows = [];

        foreach ($attributes as $name => $value) {
            if (is_scalar($value)) {
                $rows[] = ['name' => (string) $name, 'value' => (string) $value];
            }
        }

        return $rows;
    }

    /**
     * Pazaryeri `merchantSku`'yu buyuk harfe cevirir ve bosluk kabul etmez
     * (HEPSIBURADA.md §3 H8); eslesme normalize hali uzerinden kurulur.
     */
    private function normalize(string $sku): string
    {
        return mb_strtoupper(str_replace(' ', '', $sku), 'UTF-8');
    }
}
