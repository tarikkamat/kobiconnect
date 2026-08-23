<?php

declare(strict_types=1);

namespace App\Actions\Catalog\Ai;

use App\Ai\Agents\MarketplaceSeoOptimizerAgent;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Laravel\Ai\Image;
use Laravel\Ai\Responses\StructuredAgentResponse;

final class OptimizeProductMediaAndContent
{
    /**
     * @return array<string, mixed>
     */
    public function generateSeoContent(Product $product): array
    {
        $agent = new MarketplaceSeoOptimizerAgent;

        $categoryName = $product->category instanceof Category ? $product->category->name : 'Genel';
        $brandName = $product->brand instanceof Brand ? $product->brand->name : 'Belirtilmemiş';

        $prompt = sprintf(
            "Ürün Adı: %s\nMevcut Açıklama: %s\nKategori: %s\nMarka: %s\nÖzellikler: %s",
            $product->name,
            $product->description ?? 'Yok',
            $categoryName,
            $brandName,
            json_encode($product->attributes ?? [], JSON_UNESCAPED_UNICODE)
        );

        $response = $agent->prompt($prompt);
        /** @var array<string, mixed> $data */
        $data = $response instanceof StructuredAgentResponse ? $response->toArray() : (array) json_decode($response->text, true);

        return [
            'product_id' => $product->id,
            'trendyol_title' => $data['trendyol_title'] ?? $product->name,
            'trendyol_keywords' => $data['trendyol_keywords'] ?? [],
            'amazon_title' => $data['amazon_title'] ?? $product->name,
            'amazon_bullets' => $data['amazon_bullets'] ?? [],
            'amazon_search_terms' => $data['amazon_search_terms'] ?? '',
            'hepsiburada_title' => $data['hepsiburada_title'] ?? $product->name,
            'hepsiburada_description' => $data['hepsiburada_description'] ?? ($product->description ?? ''),
            'meta_description' => $data['meta_description'] ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateStudioImage(Product $product, ?string $customInstruction = null): array
    {
        $prompt = sprintf(
            'Professional high-end e-commerce studio product photograph of %s. Clean pure white background, soft studio lighting, ultra-detailed, crisp focus, 4k resolution, no watermark, commercial product shot. %s',
            $product->name,
            $customInstruction ? 'Additional detail: '.$customInstruction : ''
        );

        $generatedImage = Image::of($prompt)
            ->square()
            ->quality('high')
            ->generate();

        $path = $generatedImage->store('products/ai');

        $maxPosition = (int) ($product->images()->max('position') ?? 0);
        $productImage = ProductImage::create([
            'product_id' => $product->id,
            'url' => $path,
            'position' => $maxPosition + 1,
        ]);

        return [
            'success' => true,
            'image_id' => $productImage->id,
            'url' => $path,
            'product_id' => $product->id,
        ];
    }
}
