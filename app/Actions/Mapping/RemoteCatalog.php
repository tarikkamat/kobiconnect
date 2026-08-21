<?php

declare(strict_types=1);

namespace App\Actions\Mapping;

use App\Marketplaces\Contracts\SupportsBrandCatalog;
use App\Marketplaces\Contracts\SupportsCategoryCatalog;
use App\Marketplaces\Data\AttributeData;
use App\Marketplaces\Data\BrandData;
use App\Marketplaces\Data\CategoryNodeData;
use App\Models\ChannelConnection;
use App\Support\Sync\ConnectionDriver;

/**
 * Sihirbazin pazaryeri referans verisine tek kapisi.
 *
 * Surucu yanitlari zaten TTL'li cache'te (marka 1 gun, kategori/attribute 7
 * gun — BACKEND-PLAN §7.6) ve bu uclarin butcesi dakikada 50 istek
 * (TRENDYOL.md §7.2). Burasi ayrica istek suresince bellekte tutar: tek bir
 * sihirbaz render'i agaci hem oneri, hem arama, hem de dogrulama icin okur ve
 * bunlarin ucu de ayni cozumlemeyi paylasmali.
 *
 * Bellek istek omurludur (Octane altinda bile), cunku ornek konteynerden her
 * istekte yeniden cozulur — static tutulsaydi tenant A'nin agaci tenant B'ye
 * sizardi (.ai/rules/providers.md).
 *
 * Kategori kataloğunu desteklemeyen bir surucu bos liste dondurur; sihirbaz
 * "bu pazaryeri kategori eslemesi sunmuyor" diyebilsin diye istisna atmaz.
 */
final class RemoteCatalog
{
    /** @var array<int, list<array{remoteId: string, name: string, path: string, isLeaf: bool}>> */
    private array $categories = [];

    /** @var array<string, list<AttributeData>> */
    private array $attributes = [];

    public function __construct(private readonly ConnectionDriver $drivers) {}

    /**
     * Duzlestirilmis kategori agaci, kok-yaprak yolu ile birlikte.
     *
     * Agac prop olarak KOMPLE gonderilmez: Trendyol'un agacinda on binlerce
     * dugum var ve her sihirbaz acilisinda megabaytlarca JSON tasimak anlamsiz.
     * Arama ve oneri burada, sunucuda calisir.
     *
     * @return list<array{remoteId: string, name: string, path: string, isLeaf: bool}>
     */
    public function categories(ChannelConnection $connection): array
    {
        $key = (int) $connection->getKey();

        if (! isset($this->categories[$key])) {
            $driver = $this->drivers->for($connection);

            $this->categories[$key] = $driver instanceof SupportsCategoryCatalog
                ? $this->flatten($driver->categoryTree())
                : [];
        }

        return $this->categories[$key];
    }

    /**
     * @return array{remoteId: string, name: string, path: string, isLeaf: bool}|null
     */
    public function category(ChannelConnection $connection, string $remoteId): ?array
    {
        foreach ($this->categories($connection) as $category) {
            if ($category['remoteId'] === $remoteId) {
                return $category;
            }
        }

        return null;
    }

    /**
     * Yalnizca yaprak kategoriler — Trendyol ust kategoriye urun kabul etmez
     * (TRENDYOL.md §9, BACKEND-PLAN §7.5).
     *
     * @return list<array{remoteId: string, name: string, path: string, isLeaf: bool}>
     */
    public function leaves(ChannelConnection $connection): array
    {
        return array_values(array_filter(
            $this->categories($connection),
            static fn (array $category): bool => $category['isLeaf'],
        ));
    }

    /**
     * @return list<AttributeData>
     */
    public function attributes(ChannelConnection $connection, string $remoteCategoryId): array
    {
        $key = $connection->getKey().':'.$remoteCategoryId;

        if (! isset($this->attributes[$key])) {
            $driver = $this->drivers->for($connection);

            $this->attributes[$key] = $driver instanceof SupportsCategoryCatalog
                ? $driver->categoryAttributes($remoteCategoryId)
                : [];
        }

        return $this->attributes[$key];
    }

    /**
     * Trendyol'un marka aramasi BUYUK/KUCUK HARF DUYARLIDIR ve surucu yalnizca
     * birebir eslesmeyi dondurur (TRENDYOL.md §4.1.2) — yaklasik eslesmeyle
     * yayina cikan urun reddedilir. Bu yuzden burada da "bulunamadi" bir hata
     * degil, kullaniciya anlatilacak bir durumdur.
     */
    public function findBrand(ChannelConnection $connection, string $name): ?BrandData
    {
        $driver = $this->drivers->for($connection);

        return $driver instanceof SupportsBrandCatalog
            ? $driver->findBrandByName($name)
            : null;
    }

    /**
     * @param  list<CategoryNodeData>  $nodes
     * @return list<array{remoteId: string, name: string, path: string, isLeaf: bool}>
     */
    private function flatten(array $nodes, string $prefix = ''): array
    {
        $flat = [];

        foreach ($nodes as $node) {
            $path = $prefix === '' ? $node->name : "{$prefix} > {$node->name}";

            $flat[] = [
                'remoteId' => $node->remoteId,
                'name' => $node->name,
                'path' => $path,
                'isLeaf' => $node->isLeaf,
            ];

            foreach ($this->flatten($node->children, $path) as $child) {
                $flat[] = $child;
            }
        }

        return $flat;
    }
}
