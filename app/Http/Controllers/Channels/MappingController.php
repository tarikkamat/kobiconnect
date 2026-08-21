<?php

declare(strict_types=1);

namespace App\Http\Controllers\Channels;

use App\Actions\Mapping\RemoteCatalog;
use App\Actions\Mapping\SuggestMapping;
use App\Actions\Mapping\ValidateMapping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channels\MappingAttributeRequest;
use App\Http\Requests\Channels\MappingBrandRequest;
use App\Http\Requests\Channels\MappingCategoryRequest;
use App\Http\Requests\Channels\MappingValueRequest;
use App\Marketplaces\Data\AttributeData;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Trendyol\Exceptions\TrendyolApiException;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChannelAttributeMapping;
use App\Models\ChannelAttributeValueMapping;
use App\Models\ChannelBrandMapping;
use App\Models\ChannelCategoryMapping;
use App\Models\ChannelConnection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Kategori / ozellik / deger / marka esleme sihirbazi — FRONTEND-PLAN §4.1.
 *
 * Urun gonderiminin on kosulu olan ekran. Bes adim tek Inertia sayfasinda
 * yasar; adimlar arasi gecis sunucuya UGRAMAZ, cunku referans veri uclarinin
 * butcesi dakikada 50 istek (TRENDYOL.md §7.2) ve her adimda yeniden cekmek
 * bu butceyi anlamsizca yakar. Sunucuya yalnizca KAYIT icin gidilir.
 *
 * TASLAK SAKLAMA KARARI: ayri bir "taslak" tablosu yok. Her adim kendi
 * satirlarini `channel_*_mappings` tablolarina hemen yazar; eksik esleme zaten
 * taslagin ta kendisidir ve onizleme adimi eksikleri sayar. Boylece sihirbaz
 * yarida birakilip gunler sonra devam ettirilebilir, iki kaynak arasinda
 * senkron tutulacak bir sey olmaz ve yarim kalmis esleme kimseyi yaniltmaz —
 * gonderim zaten ayni dogrulamadan geciyor (BACKEND-PLAN §7.5). Hangi adimda
 * kalindigi ise sunucuyu ilgilendirmez, istemcide `useRemember` ile durur.
 *
 * Kayit metotlari bagli modelleri kullanmasalar bile TIP BELIRTEREK alir:
 * SubstituteBindings, ortuk baglamayi controller metodunun imzasindan cikarir
 * ve imzada yoksa FormRequest icinde `$this->route('connection')` ham string
 * doner — yaprak kontrolu de kilit kontrolu de sessizce calismaz.
 */
class MappingController extends Controller
{
    /**
     * Referans veri okunamadiginda sayfa 500 vermez: sihirbaz eldeki
     * eslemelerle acilir ve sebep ust bantta yazar.
     */
    private ?string $catalogError = null;

    public function __construct(
        private readonly RemoteCatalog $catalog,
        private readonly SuggestMapping $suggest,
        private readonly ValidateMapping $validation,
    ) {}

    /**
     * Kendi kategori agacimiz ve her dugumun esleme durumu.
     *
     * Durum YALNIZCA veritabanindan turetilir — kategori basina pazaryeri
     * ozellik listesi cekmek N istek demek olurdu ve liste 200 kategoriyle
     * dakikalik butceyi asardi. Kesin sonuc sihirbazin onizleme adiminda.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ChannelConnection::class);

        $connections = ChannelConnection::query()->orderBy('name')->get(['id', 'name', 'marketplace']);
        $connection = $connections->firstWhere('id', $request->integer('connection')) ?? $connections->first();

        return Inertia::render('channels/mapping/index', [
            'connections' => $connections
                ->map(fn (ChannelConnection $row): array => [
                    'id' => $row->getKey(),
                    'name' => $row->name,
                ])
                ->all(),
            'connectionId' => $connection?->getKey(),
            'categories' => $connection === null ? [] : $this->categoryRows($connection),
        ]);
    }

    public function show(Request $request, ChannelConnection $connection, Category $category): Response
    {
        Gate::authorize('view', $connection);

        $mapping = $this->validation->categoryMapping($connection, $category);
        $remoteCategory = $mapping === null
            ? null
            : $this->safely(fn (): ?array => $this->catalog->category($connection, $mapping->remote_category_id));

        $search = trim((string) $request->query('q', ''));
        $brandQuery = trim((string) $request->query('brand', ''));

        return Inertia::render('channels/mapping/wizard', [
            'connection' => [
                'id' => $connection->getKey(),
                'name' => $connection->name,
            ],
            'category' => [
                'id' => $category->getKey(),
                'name' => $category->name,
                'path' => $this->categoryPath($category),
                'productCount' => Product::query()->where('category_id', $category->getKey())->count(),
            ],
            'mapping' => $mapping === null ? null : [
                'remoteCategoryId' => $mapping->remote_category_id,
                'remotePath' => $mapping->remote_path ?? $remoteCategory['path'] ?? $mapping->remote_category_id,
                'isLeaf' => $remoteCategory['isLeaf'] ?? false,
            ],
            // Onaydan sonra kategori, `varianter` ve `slicer` degistirilemez
            // (TRENDYOL.md §9.3, §9.7); arayuz bu alanlari kilitler ve sebebi
            // tooltip'e koyar.
            'lock' => $this->lock($connection, $category),
            // Isim benzerligine dayali oneri; yalnizca yaprak kategoriler.
            'suggestions' => fn (): array => $this->categorySuggestions($connection, $category),
            'search' => $search,
            'searchResults' => Inertia::optional(fn (): array => $this->categorySearch($connection, $search)),
            'attributes' => fn (): array => $mapping === null
                ? []
                : $this->attributeRows($connection, $mapping->remote_category_id),
            'localAttributes' => fn (): array => Attribute::query()
                ->with('values')
                ->orderBy('name')
                ->get()
                ->map(fn (Attribute $attribute): array => [
                    'id' => $attribute->getKey(),
                    'name' => $attribute->name,
                    'values' => $attribute->values
                        ->map(fn (AttributeValue $value): array => [
                            'id' => $value->getKey(),
                            'value' => $value->value,
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
            'brands' => fn (): array => $this->brandRows($connection, $category),
            'brandSearch' => $brandQuery,
            'brandResult' => Inertia::optional(fn (): array => $this->brandSearch($connection, $brandQuery)),
            'issues' => fn (): array => $this->validation->handle(
                $connection,
                $category,
                $mapping === null ? [] : $this->safely(
                    fn (): array => $this->catalog->attributes($connection, $mapping->remote_category_id),
                    [],
                ),
                $remoteCategory,
            ),
            // Sirasi onemli: yukaridaki kapanislar calistiktan SONRA okunur.
            'catalogError' => fn (): ?string => $this->catalogError,
        ]);
    }

    public function storeCategory(
        MappingCategoryRequest $request,
        ChannelConnection $connection,
        Category $category,
    ): RedirectResponse {
        Gate::authorize('update', $connection);

        $existing = $this->validation->categoryMapping($connection, $category);
        $remoteId = $request->string('remote_category_id')->toString();
        $remote = $this->safely(fn (): ?array => $this->catalog->category($connection, $remoteId));

        DB::transaction(function () use ($connection, $category, $existing, $remoteId, $remote): void {
            if ($existing !== null && $existing->remote_category_id !== $remoteId) {
                $this->forgetAttributeMappings($connection, $category, $existing->remote_category_id);
            }

            ChannelCategoryMapping::query()->updateOrCreate(
                ['connection_id' => $connection->getKey(), 'category_id' => $category->getKey()],
                ['remote_category_id' => $remoteId, 'remote_path' => $remote['path'] ?? null],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori eşlemesi kaydedildi.']);

        return back();
    }

    /**
     * Bayraklar istekten DEGIL, referans katalogundan kopyalanir: `required`,
     * `allowCustom`, `allowMultipleAttributeValues`, `varianter` ve `slicer`
     * pazaryerinin gercegidir ve yerel on-dogrulama bunlarin uzerine kurulu
     * (BACKEND-PLAN §7.5, TRENDYOL.md §9.7).
     */
    public function storeAttributes(
        MappingAttributeRequest $request,
        ChannelConnection $connection,
        Category $category,
    ): RedirectResponse {
        Gate::authorize('update', $connection);

        $remoteCategoryId = (string) $this->validation
            ->categoryMapping($connection, $category)?->remote_category_id;

        $remote = $request->remoteAttributes();
        $selection = $request->selection();

        DB::transaction(function () use ($connection, $remoteCategoryId, $remote, $selection): void {
            $stored = ChannelAttributeMapping::query()
                ->where('connection_id', $connection->getKey())
                ->where('remote_category_id', $remoteCategoryId)
                ->get();

            // Cikarilan VE baska bir yerel ozellige tasinan satirlar silinir;
            // silme, bagli deger eslemelerini de goturur (FK cascade), ki eski
            // ozellige ait deger eslemeleri yeni ozellige yapismasin.
            foreach ($stored as $row) {
                if (($selection[$row->remote_attribute_id] ?? null) !== $row->attribute_id) {
                    $row->delete();
                }
            }

            foreach ($selection as $remoteAttributeId => $attributeId) {
                $attribute = $remote[$remoteAttributeId];

                ChannelAttributeMapping::query()->updateOrCreate(
                    [
                        'connection_id' => $connection->getKey(),
                        'remote_category_id' => $remoteCategoryId,
                        'attribute_id' => $attributeId,
                    ],
                    [
                        'remote_attribute_id' => $remoteAttributeId,
                        'is_required' => $attribute->isRequired,
                        'allow_custom' => $attribute->allowsCustomValue,
                        'allow_multiple' => $attribute->allowsMultipleValues,
                        'is_varianter' => $attribute->isVarianter,
                        'is_slicer' => $attribute->isSlicer,
                    ],
                );
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Özellik eşlemesi kaydedildi.']);

        return back();
    }

    public function storeValues(
        MappingValueRequest $request,
        ChannelConnection $connection,
        Category $category,
    ): RedirectResponse {
        Gate::authorize('update', $connection);

        $mappingIds = $request->attributeMappingIds();
        $rows = $request->rows();

        DB::transaction(function () use ($mappingIds, $rows): void {
            // Adim butun halinde kaydedilir: kullanici listeden bir esleme
            // kaldirdiginda tek tek diff cikarmak yerine bu kategorinin deger
            // eslemeleri yeniden yazilir.
            ChannelAttributeValueMapping::query()->whereIn('mapping_id', $mappingIds)->delete();

            foreach ($rows as $row) {
                ChannelAttributeValueMapping::query()->create([
                    'mapping_id' => (int) $row['mapping_id'],
                    'attribute_value_id' => (int) $row['attribute_value_id'],
                    'remote_value_id' => $row['remote_value_id'],
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Değer eşlemesi kaydedildi.']);

        return back();
    }

    /**
     * Marka eslemesi BAGLANTI kapsamlidir, kategori kapsamli degil: sihirbaz
     * yalnizca bu kategorideki urunlerin markalarini gosterdigi icin burada
     * "eksik olani sil" yapilmaz, sadece gelenler yazilir. Aksi halde baska bir
     * kategoride yapilmis marka eslemesi sessizce silinirdi.
     */
    public function storeBrands(
        MappingBrandRequest $request,
        ChannelConnection $connection,
        Category $category,
    ): RedirectResponse {
        Gate::authorize('update', $connection);

        foreach ($request->rows() as $row) {
            ChannelBrandMapping::query()->updateOrCreate(
                ['connection_id' => $connection->getKey(), 'brand_id' => (int) $row['brand_id']],
                ['remote_brand_id' => $row['remote_brand_id']],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marka eşlemesi kaydedildi.']);

        return back();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryRows(ChannelConnection $connection): array
    {
        $mappings = ChannelCategoryMapping::query()
            ->where('connection_id', $connection->getKey())
            ->get()
            ->keyBy('category_id');

        $attributeCounts = ChannelAttributeMapping::query()
            ->where('connection_id', $connection->getKey())
            ->selectRaw('remote_category_id, count(*) as total, sum(case when is_varianter then 1 else 0 end) as varianters')
            ->groupBy('remote_category_id')
            ->get()
            ->keyBy('remote_category_id');

        $productCounts = Product::query()
            ->selectRaw('category_id, count(*) as total')
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        return array_values(Category::query()
            ->orderBy('path')
            ->get()
            ->map(function (Category $category) use ($mappings, $attributeCounts, $productCounts): array {
                $mapping = $mappings->get($category->getKey());
                $counts = $mapping === null ? null : $attributeCounts->get($mapping->remote_category_id);

                return [
                    'id' => $category->getKey(),
                    'name' => $category->name,
                    'depth' => substr_count($category->path, '/'),
                    'productCount' => (int) ($productCounts[$category->getKey()] ?? 0),
                    'remotePath' => $mapping === null
                        ? null
                        : ($mapping->remote_path ?? $mapping->remote_category_id),
                    'attributeCount' => (int) ($counts->total ?? 0),
                    'status' => match (true) {
                        $mapping === null => 'unmapped',
                        (int) ($counts->total ?? 0) === 0 => 'partial',
                        (int) ($counts->varianters ?? 0) !== 1 => 'partial',
                        default => 'mapped',
                    },
                ];
            })
            ->all());
    }

    /**
     * @return array{locked: bool, reason: string|null}
     */
    private function lock(ChannelConnection $connection, Category $category): array
    {
        $locked = $this->validation->hasApprovedListings($connection, $category);

        return [
            'locked' => $locked,
            'reason' => $locked
                ? 'Bu kategoride onaylanmış listeleme var. Pazaryeri, onaydan sonra kategoriyi, varyant '
                    .'belirleyici ve ayrı ürün kartı açan özellik değerlerini değiştirmeye izin vermiyor.'
                : null,
        ];
    }

    /**
     * @return list<array{remoteId: string, path: string, score: int}>
     */
    private function categorySuggestions(ChannelConnection $connection, Category $category): array
    {
        $leaves = $this->safely(fn (): array => $this->catalog->leaves($connection), []);

        $ranked = $this->suggest->rank(
            $category->name,
            array_map(static fn (array $leaf): string => $leaf['name'], $leaves),
        );

        $suggestions = [];

        foreach ($ranked as $index => $score) {
            $suggestions[] = [
                'remoteId' => $leaves[$index]['remoteId'],
                'path' => $leaves[$index]['path'],
                'score' => $score,
            ];
        }

        return $suggestions;
    }

    /**
     * Agacin tamami prop olarak gonderilmez; arama sunucuda calisir ve ilk 50
     * eslesme doner. Yaprak olmayanlar da listelenir ama secilemez — kullanici
     * dogru dali gorup altina inebilsin diye.
     *
     * @return list<array{remoteId: string, path: string, isLeaf: bool}>
     */
    private function categorySearch(ChannelConnection $connection, string $term): array
    {
        if ($term === '') {
            return [];
        }

        $needle = SuggestMapping::normalize($term);
        $matches = [];

        foreach ($this->safely(fn (): array => $this->catalog->categories($connection), []) as $node) {
            if (str_contains(SuggestMapping::normalize($node['path']), $needle)) {
                $matches[] = [
                    'remoteId' => $node['remoteId'],
                    'path' => $node['path'],
                    'isLeaf' => $node['isLeaf'],
                ];
            }

            if (count($matches) >= 50) {
                break;
            }
        }

        return $matches;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attributeRows(ChannelConnection $connection, string $remoteCategoryId): array
    {
        $remote = $this->safely(fn (): array => $this->catalog->attributes($connection, $remoteCategoryId), []);

        $stored = ChannelAttributeMapping::query()
            ->where('connection_id', $connection->getKey())
            ->where('remote_category_id', $remoteCategoryId)
            ->with(['attribute.values', 'valueMappings'])
            ->get()
            ->keyBy('remote_attribute_id');

        $localAttributes = Attribute::query()->pluck('name', 'id')->all();

        return array_map(function (AttributeData $attribute) use ($stored, $localAttributes): array {
            $mapping = $stored->get($attribute->remoteId);

            return [
                'remoteId' => $attribute->remoteId,
                'name' => $attribute->name,
                'isRequired' => $attribute->isRequired,
                'allowCustom' => $attribute->allowsCustomValue,
                'allowMultiple' => $attribute->allowsMultipleValues,
                'isVarianter' => $attribute->isVarianter,
                'isSlicer' => $attribute->isSlicer,
                'attributeId' => $mapping?->attribute_id,
                'mappingId' => $mapping?->getKey(),
                'suggestedAttributeId' => $mapping !== null
                    ? null
                    : $this->suggest->best($attribute->name, $localAttributes),
                'values' => array_map(
                    static fn ($value): array => ['remoteId' => (string) $value->remoteId, 'value' => $value->value],
                    $attribute->values,
                ),
                'valueMappings' => $mapping === null
                    ? []
                    : $mapping->valueMappings
                        ->mapWithKeys(fn ($row): array => [$row->attribute_value_id => $row->remote_value_id])
                        ->all(),
                'suggestedValues' => $this->valueSuggestions($attribute, $mapping),
            ];
        }, $remote);
    }

    /**
     * Otomatik eslesenler on-isaretli gelir, kullanici yalnizca farklari cozer.
     *
     * @return array<int, string>
     */
    private function valueSuggestions(AttributeData $attribute, ?ChannelAttributeMapping $mapping): array
    {
        $localValues = $mapping?->attribute?->values;

        if ($localValues === null || $attribute->values === []) {
            return [];
        }

        $candidates = [];
        $remoteIds = [];

        foreach ($attribute->values as $index => $value) {
            $candidates[$index] = $value->value;
            $remoteIds[$index] = (string) $value->remoteId;
        }

        $suggestions = [];

        foreach ($localValues as $localValue) {
            $index = $this->suggest->best($localValue->value, $candidates);

            if ($index !== null && isset($remoteIds[$index])) {
                $suggestions[$localValue->getKey()] = $remoteIds[$index];
            }
        }

        return $suggestions;
    }

    /**
     * Bu kategorideki urunlerin markalari. Marka eslemesi baglanti kapsamli
     * olsa da liste kategoriyle daraltilir: tum katalog markalarini tek ekranda
     * eslettirmek sihirbazi kullanilamaz kilar.
     *
     * @return list<array{id: int, name: string, remoteBrandId: string|null}>
     */
    private function brandRows(ChannelConnection $connection, Category $category): array
    {
        $mapped = ChannelBrandMapping::query()
            ->where('connection_id', $connection->getKey())
            ->pluck('remote_brand_id', 'brand_id');

        return array_values(Brand::query()
            ->whereHas('products', fn (Builder $products) => $products->where('category_id', $category->getKey()))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Brand $brand): array => [
                'id' => (int) $brand->getKey(),
                'name' => $brand->name,
                'remoteBrandId' => $this->stringOrNull($mapped[$brand->getKey()] ?? null),
            ])
            ->all());
    }

    /**
     * Trendyol'un marka aramasi BUYUK/KUCUK HARF DUYARLIDIR ve yalnizca birebir
     * eslesme doner (TRENDYOL.md §4.1.2). Bulunamayan bir marka burada sessizce
     * yakina yuvarlanmaz — arayuz durumu aciklar, marka YARATMA bu fazda yok.
     *
     * @return array{query: string, brand: array{remoteId: string, name: string}|null}
     */
    private function brandSearch(ChannelConnection $connection, string $name): array
    {
        $brand = $name === ''
            ? null
            : $this->safely(fn () => $this->catalog->findBrand($connection, $name));

        return [
            'query' => $name,
            'brand' => $brand === null ? null : ['remoteId' => $brand->remoteId, 'name' => $brand->name],
        ];
    }

    /**
     * Ayni uzak kategoriye eslenmis BASKA bir yerel kategori kalmadiysa o
     * kategorinin ozellik eslemeleri anlamini yitirir ve silinir; kalmissa
     * dokunulmaz — `channel_attribute_mappings` yerel kategoriye degil, uzak
     * kategoriye bagli.
     */
    private function forgetAttributeMappings(
        ChannelConnection $connection,
        Category $category,
        string $remoteCategoryId,
    ): void {
        $stillUsed = ChannelCategoryMapping::query()
            ->where('connection_id', $connection->getKey())
            ->where('remote_category_id', $remoteCategoryId)
            ->where('category_id', '!=', $category->getKey())
            ->exists();

        if ($stillUsed) {
            return;
        }

        ChannelAttributeMapping::query()
            ->where('connection_id', $connection->getKey())
            ->where('remote_category_id', $remoteCategoryId)
            ->delete();
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function categoryPath(Category $category): string
    {
        $ids = array_filter(explode('/', $category->path));

        return Category::query()
            ->whereIn('id', $ids)
            ->orderBy('path')
            ->pluck('name')
            ->implode(' > ');
    }

    /**
     * Referans veri okumasi patlarsa sihirbaz acilmaya devam eder: 429, 401 ya
     * da ag hatasi kullaniciya cumleyle anlatilir, eldeki esleme kaybolmaz.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $read
     * @param  TValue  $fallback
     * @return TValue
     */
    private function safely(callable $read, mixed $fallback = null): mixed
    {
        try {
            return $read();
        } catch (Throwable $exception) {
            $this->catalogError ??= $this->explain($exception);

            return $fallback;
        }
    }

    private function explain(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof TrendyolApiException && $exception->status === 429 => 'Pazaryeri istek limiti aşıldı (429). Referans veriler birkaç dakika içinde tekrar denendiğinde gelir; mevcut eşlemeleriniz duruyor.',
            $exception instanceof TrendyolApiException && $exception->status === 401 => 'Pazaryeri kimlik doğrulamayı reddetti (401). Bağlantı ekranından API anahtarlarını kontrol edin.',
            $exception instanceof TrendyolApiException => "Pazaryeri kataloğu okunamadı ({$exception->status}).",
            $exception instanceof MarketplaceException => 'Bu pazaryeri kategori kataloğu sunmuyor; eşleme yapılamaz.',
            default => 'Pazaryeri kataloğuna ulaşılamadı. Sunucunun internet erişimini kontrol edip tekrar deneyin.',
        };
    }
}
