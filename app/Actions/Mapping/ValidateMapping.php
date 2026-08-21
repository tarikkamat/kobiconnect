<?php

declare(strict_types=1);

namespace App\Actions\Mapping;

use App\Marketplaces\Data\AttributeData;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ChannelAttributeMapping;
use App\Models\ChannelBrandMapping;
use App\Models\ChannelCategoryMapping;
use App\Models\ChannelConnection;
use App\Models\ChannelListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Yerel on-dogrulama — BACKEND-PLAN §7.5.
 *
 * Kullanici gonderimden sonra 4 saat bekleyip "reddedildi" gormemeli: kategori
 * attribute bayraklari (`required`, `allowCustom`, `varianter`, `slicer`) ve
 * "yalnizca yaprak kategori" kurali gonderimden ONCE burada kontrol edilir.
 *
 * Bu sinif urun gonderiminin de on kosulu olacak (ayni kurallar, ayni yer);
 * bugun yalnizca sihirbazin onizleme adimi cagiriyor.
 */
final class ValidateMapping
{
    /**
     * Onay durumu `channel_listings.remote_status` uzerinden okunur; Trendyol'da
     * "onayla" diye bir servis yok, onay bir DURUMDUR (TRENDYOL.md §9.3).
     */
    private const string APPROVED = 'approved';

    /**
     * Eslemenin eksiklerini insan-okunur maddeler halinde dondurur.
     *
     * Pazaryeri attribute'lari cagirana parametre olarak gecilir: sihirbaz onlari
     * ayni istekte zaten okumus oluyor ve referans uclarinin dakikalik butcesi
     * 50 istek (TRENDYOL.md §7.2) — ayni veriyi ikinci kez cekmek icin sebep yok.
     *
     * @param  list<AttributeData>  $remoteAttributes
     * @param  array{remoteId: string, name: string, path: string, isLeaf: bool}|null  $remoteCategory
     * @return list<array{level: string, message: string}>
     */
    public function handle(
        ChannelConnection $connection,
        Category $category,
        array $remoteAttributes = [],
        ?array $remoteCategory = null,
    ): array {
        $mapping = $this->categoryMapping($connection, $category);

        if ($mapping === null) {
            return [$this->error('Kategori henüz bir pazaryeri kategorisine eşlenmedi.')];
        }

        if ($remoteCategory === null) {
            return [$this->error(
                'Eşlenen pazaryeri kategorisi ('.$mapping->remote_category_id.') artık katalogda yok. '
                .'Pazaryeri kategori ağacını değiştirmiş olabilir; kategoriyi yeniden eşleyin.'
            )];
        }

        return [
            ...$this->categoryIssues($remoteCategory),
            ...$this->attributeIssues($connection, $mapping, $remoteAttributes),
            ...$this->valueIssues($connection, $mapping),
            ...$this->brandIssues($connection, $category),
        ];
    }

    /**
     * Onaylanmis bir listeleme varsa `slicer` ve `varianter` degerleri ARTIK
     * DEGISTIRILEMEZ (TRENDYOL.md §9.3, §9.7): degistirilirse pazaryeri kalem
     * duzeyinde sessizce reddeder. Ayni sebeple kategori de sabitlenir —
     * onayli urunde `categoryId` degismez.
     */
    public function hasApprovedListings(ChannelConnection $connection, Category $category): bool
    {
        return ChannelListing::query()
            ->where('connection_id', $connection->getKey())
            ->where('remote_status', self::APPROVED)
            ->whereHas('variant', fn (Builder $variant) => $variant->whereHas(
                'product',
                fn (Builder $product) => $product->where('category_id', $category->getKey()),
            ))
            ->exists();
    }

    public function categoryMapping(ChannelConnection $connection, Category $category): ?ChannelCategoryMapping
    {
        return ChannelCategoryMapping::query()
            ->where('connection_id', $connection->getKey())
            ->where('category_id', $category->getKey())
            ->first();
    }

    /**
     * @param  array{remoteId: string, name: string, path: string, isLeaf: bool}  $remoteCategory
     * @return list<array{level: string, message: string}>
     */
    private function categoryIssues(array $remoteCategory): array
    {
        if ($remoteCategory['isLeaf']) {
            return [];
        }

        return [$this->error(
            '"'.$remoteCategory['path'].'" bir üst kategori. Pazaryeri yalnızca alt kategorisi olmayan '
            .'(yaprak) kategorilere ürün kabul eder; bu haliyle aktarım yapılamaz.'
        )];
    }

    /**
     * @param  list<AttributeData>  $remoteAttributes
     * @return list<array{level: string, message: string}>
     */
    private function attributeIssues(
        ChannelConnection $connection,
        ChannelCategoryMapping $mapping,
        array $remoteAttributes,
    ): array {
        $mapped = $this->attributeMappings($connection, $mapping->remote_category_id);
        $issues = [];

        foreach ($remoteAttributes as $attribute) {
            if ($attribute->isRequired && ! $mapped->has($attribute->remoteId)) {
                $issues[] = $this->error(
                    '"'.$attribute->name.'" özelliği bu kategoride zorunlu ama eşlenmemiş.'
                );
            }
        }

        $varianters = $mapped->filter(fn (ChannelAttributeMapping $row): bool => $row->is_varianter);

        if ($varianters->count() > 1) {
            $issues[] = $this->error(
                'Kategori başına yalnızca bir varyant belirleyici özellik olabilir; '
                .$varianters->count().' tanesi eşlenmiş. Fazlalıkları kaldırın.'
            );
        }

        if ($varianters->isEmpty() && $this->hasVarianterOption($remoteAttributes)) {
            $issues[] = $this->error(
                'Bu kategoride varyant belirleyici bir özellik eşlenmemiş; varyantlar tek listelemeye katlanır.'
            );
        }

        foreach ($mapped->filter(fn (ChannelAttributeMapping $row): bool => $row->is_slicer) as $slicer) {
            $issues[] = $this->warning(
                '"'.$slicer->attribute->name.'" ayrı ürün kartı açar: '
                .'bu özelliğin her değeri pazaryerinde bağımsız bir ürün olarak listelenir.'
            );
        }

        return $issues;
    }

    /**
     * `allowCustom = false` olan bir attribute'ta serbest metin reddedilir;
     * degerler eslenmeden o kategoride urun yayinlanamaz (TRENDYOL.md §9.7).
     *
     * @return list<array{level: string, message: string}>
     */
    private function valueIssues(ChannelConnection $connection, ChannelCategoryMapping $mapping): array
    {
        $issues = [];

        $rows = ChannelAttributeMapping::query()
            ->where('connection_id', $connection->getKey())
            ->where('remote_category_id', $mapping->remote_category_id)
            ->where('allow_custom', false)
            ->with(['attribute.values', 'valueMappings'])
            ->get();

        foreach ($rows as $row) {
            $mappedValues = $row->valueMappings->pluck('attribute_value_id')->all();
            $missing = $row->attribute->values->whereNotIn('id', $mappedValues)->count();

            if ($missing > 0) {
                $issues[] = $this->error(
                    '"'.$row->attribute->name.'" özelliği serbest metin kabul etmiyor; '
                    .$missing.' değeriniz pazaryeri değerine eşlenmemiş.'
                );
            }
        }

        return $issues;
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    private function brandIssues(ChannelConnection $connection, Category $category): array
    {
        $mapped = ChannelBrandMapping::query()
            ->where('connection_id', $connection->getKey())
            ->pluck('brand_id')
            ->all();

        $unmapped = Brand::query()
            ->whereNotIn('id', $mapped)
            ->whereHas('products', fn (Builder $products) => $products->where('category_id', $category->getKey()))
            ->orderBy('name')
            ->pluck('name')
            ->all();

        if ($unmapped === []) {
            return [];
        }

        return [$this->error(
            'Bu kategorideki ürünlerin markaları pazaryeri markasına eşlenmemiş: '.implode(', ', $unmapped).'.'
        )];
    }

    /**
     * @return Collection<string, ChannelAttributeMapping>
     */
    private function attributeMappings(ChannelConnection $connection, string $remoteCategoryId): Collection
    {
        return ChannelAttributeMapping::query()
            ->where('connection_id', $connection->getKey())
            ->where('remote_category_id', $remoteCategoryId)
            ->with('attribute')
            ->get()
            ->keyBy('remote_attribute_id');
    }

    /**
     * @param  list<AttributeData>  $remoteAttributes
     */
    private function hasVarianterOption(array $remoteAttributes): bool
    {
        foreach ($remoteAttributes as $attribute) {
            if ($attribute->isVarianter) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{level: string, message: string}
     */
    private function error(string $message): array
    {
        return ['level' => 'error', 'message' => $message];
    }

    /**
     * @return array{level: string, message: string}
     */
    private function warning(string $message): array
    {
        return ['level' => 'warning', 'message' => $message];
    }
}
