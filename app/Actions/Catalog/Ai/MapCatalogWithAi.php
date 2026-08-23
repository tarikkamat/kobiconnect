<?php

declare(strict_types=1);

namespace App\Actions\Catalog\Ai;

use App\Ai\Agents\AutonomousCatalogMapperAgent;
use App\Models\Product;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class MapCatalogWithAi
{
    /**
     * @param  Collection<int, Product>|array<int, Product>  $products
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(Collection|array $products, string $targetMarketplace = 'trendyol'): array
    {
        $results = [];
        $agent = new AutonomousCatalogMapperAgent;

        foreach ($products as $product) {
            $images = $product->images->pluck('url')->all();
            /** @var array<string, mixed> $currentAttrs */
            $currentAttrs = (array) ($product->getAttribute('attributes') ?? []);

            $prompt = sprintf(
                "Ürün Adı: %s\nAçıklama: %s\nMevcut Özellikler: %s\nGörseller: %s\nHedef Pazaryeri: %s\n\nLütfen bu ürün için zorunlu ve önerilen pazaryeri kategori ve özellik eşlemelerini çıkar.",
                $product->name,
                $product->description ?? 'Açıklama yok',
                json_encode($currentAttrs, JSON_UNESCAPED_UNICODE),
                implode(', ', $images),
                $targetMarketplace
            );

            $response = $agent->prompt($prompt);
            /** @var array<string, mixed> $data */
            $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

            /** @var array<string, mixed> $mappedAttributes */
            $mappedAttributes = $currentAttrs;
            if (isset($data['attributes']) && is_array($data['attributes'])) {
                foreach ($data['attributes'] as $attr) {
                    if (is_array($attr) && isset($attr['name'], $attr['value'])) {
                        $mappedAttributes[(string) $attr['name']] = $attr['value'];
                    }
                }
            }

            if (isset($data['extracted_specs']) && is_array($data['extracted_specs'])) {
                foreach ($data['extracted_specs'] as $k => $v) {
                    if ($v) {
                        $mappedAttributes[(string) $k] = $v;
                    }
                }
            }

            $product->update(['attributes' => $mappedAttributes]);

            $results[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'target_marketplace' => $targetMarketplace,
                'suggested_category' => $data['suggested_category'] ?? 'Genel',
                'extracted_specs' => $data['extracted_specs'] ?? [],
                'attributes' => $data['attributes'] ?? [],
            ];
        }

        return $results;
    }
}
