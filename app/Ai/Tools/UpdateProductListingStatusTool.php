<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\ListingSyncState;
use App\Enums\ProductStatus;
use App\Models\ChannelListing;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateProductListingStatusTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Belirtilen ürünlerin satış durumunu değiştirir (örneğin kârsız veya yüksek iadeli ürünleri satışa kapatır/duraklatır veya aktifleştirir).';
    }

    public function handle(Request $request): Stringable|string
    {
        $productIds = (array) ($request['product_ids'] ?? []);
        $action = (string) ($request['action'] ?? 'pause'); // pause, unpublish, activate

        if (empty($productIds)) {
            return (string) json_encode([
                'success' => false,
                'message' => 'Güncellenecek ürün ID listesi verilmedi.',
            ], JSON_UNESCAPED_UNICODE);
        }

        $products = Product::whereIn('id', $productIds)->get();
        $updated = [];

        foreach ($products as $product) {
            $newStatus = match ($action) {
                'activate' => ProductStatus::Active,
                'unpublish', 'pause' => ProductStatus::Draft,
                default => ProductStatus::Draft,
            };

            $product->update(['status' => $newStatus]);

            // İlgili pazaryeri listelemelerini de pasife al
            ChannelListing::whereHas('variant', fn ($q) => $q->where('product_id', $product->id))
                ->update([
                    'sync_state' => $action === 'activate' ? ListingSyncState::Synced : ListingSyncState::Pending,
                ]);

            $updated[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'new_status' => $newStatus->value,
            ];
        }

        return (string) json_encode([
            'success' => true,
            'action_taken' => $action,
            'updated_products' => $updated,
            'message' => count($updated).' adet ürün başarıyla '.($action === 'activate' ? 'satışa açıldı' : 'satışa kapatıldı/duraklatıldı').'.',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'product_ids' => $schema->array()->items($schema->integer())->required()->description('Durumu değiştirilecek ürün ID listesi'),
            'action' => $schema->string()->enum(['pause', 'unpublish', 'activate'])->required()->description('Uygulanacak eylem: pause/unpublish (satışa kapat) veya activate (satışa aç)'),
        ];
    }
}
