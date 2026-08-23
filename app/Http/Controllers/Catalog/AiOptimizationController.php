<?php

declare(strict_types=1);

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\Ai\OptimizeProductMediaAndContent;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiOptimizationController extends Controller
{
    public function seo(Product $product, OptimizeProductMediaAndContent $optimizer): JsonResponse
    {
        $product->loadMissing(['category', 'brand', 'variants']);
        $seoData = $optimizer->generateSeoContent($product);

        return response()->json([
            'success' => true,
            'seo' => $seoData,
        ]);
    }

    public function image(Request $request, Product $product, OptimizeProductMediaAndContent $optimizer): JsonResponse
    {
        $validated = $request->validate([
            'instruction' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $optimizer->generateStudioImage($product, $validated['instruction'] ?? null);

        return response()->json([
            'success' => true,
            'image' => $result,
        ]);
    }

    public function generateImage(Request $request, OptimizeProductMediaAndContent $optimizer): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'instruction' => ['nullable', 'string', 'max:500'],
            'image_url' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $optimizer->refactorStudioImage(
            $validated['image_url'] ?? null,
            $validated['name'],
            $validated['instruction'] ?? null
        );

        return response()->json([
            'success' => true,
            'image' => $result,
        ]);
    }
}
