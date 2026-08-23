<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\Ai\MapCatalogWithAi;
use App\Ai\Agents\AutonomousCatalogMapperAgent;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\StructuredAgentResponse;

class AiCatalogMappingController extends Controller
{
    public function map(Request $request, MapCatalogWithAi $mapCatalog): JsonResponse
    {
        $validated = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'target_marketplace' => ['nullable', 'string', 'in:trendyol,hepsiburada,amazon,ciceksepeti'],
        ]);

        $products = Product::with(['images', 'variants', 'category', 'brand'])
            ->whereIn('id', $validated['product_ids'])
            ->get();

        $results = $mapCatalog($products, $validated['target_marketplace'] ?? 'trendyol');

        return response()->json([
            'success' => true,
            'mapped_count' => count($results),
            'results' => $results,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'raw_attributes' => ['nullable', 'array'],
            'images' => ['nullable', 'array'],
            'target_marketplace' => ['nullable', 'string'],
        ]);

        $agent = new AutonomousCatalogMapperAgent;
        $targetMarketplace = $validated['target_marketplace'] ?? 'trendyol';

        $prompt = sprintf(
            "Ürün Başlığı: %s\nAçıklama: %s\nHam Nitelikler: %s\nGörseller: %s\nHedef Pazaryeri: %s\n\nLütfen sıfır kural katalog eşlemesini yap.",
            $validated['title'],
            $validated['description'] ?? 'Yok',
            json_encode($validated['raw_attributes'] ?? [], JSON_UNESCAPED_UNICODE),
            json_encode($validated['images'] ?? [], JSON_UNESCAPED_UNICODE),
            $targetMarketplace
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return response()->json([
            'success' => true,
            'preview' => [
                'title' => $validated['title'],
                'target_marketplace' => $targetMarketplace,
                'suggested_category' => $data['suggested_category'] ?? 'Genel',
                'extracted_specs' => $data['extracted_specs'] ?? [],
                'attributes' => $data['attributes'] ?? [],
            ],
        ]);
    }
}
