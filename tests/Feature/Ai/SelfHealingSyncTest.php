<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Actions\Sync\Ai\HealFailedOperation;
use App\Ai\Agents\SelfHealingSyncAgent;
use App\Marketplaces\Data\Enums\OperationType;
use App\Marketplaces\Data\Enums\SyncState;
use App\Models\ChannelConnection;
use App\Models\ChannelOperation;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\TenantRoleSeeder;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);
});

it('self-heals missing mandatory attributes and requeues operation', function (): void {
    SelfHealingSyncAgent::fake([
        [
            'repaired' => true,
            'error_code' => 'MISSING_MANDATORY_ATTRIBUTE',
            'root_cause' => 'Trendyol materyal özelliği zorunludur.',
            'missing_attribute_name' => 'Materyal',
            'extracted_value' => 'Hakiki Deri',
            'repaired_payload' => [
                'attributes' => [
                    ['attributeId' => 338, 'attributeValueId' => 10],
                    ['attributeId' => 342, 'attributeValueId' => 55, 'customValue' => 'Hakiki Deri'],
                ],
            ],
            'repair_summary' => 'Ürün açıklamasındaki %100 Dana Derisi ifadesinden Materyal özelliği Hakiki Deri olarak çıkarıldı ve payload onarıldı.',
        ],
    ]);

    $connection = ChannelConnection::factory()->create(['marketplace' => 'trendyol']);
    $product = Product::factory()->create([
        'name' => 'Erkek Kahverengi Klasik Deri Cüzdan',
        'description' => '%100 Dana Derisi el yapımı cüzdan.',
        'attributes' => [],
    ]);

    $operation = ChannelOperation::factory()->create([
        'connection_id' => $connection->id,
        'entity_type' => 'product',
        'entity_id' => $product->id,
        'operation' => OperationType::ProductCreate->value,
        'status' => SyncState::Failed,
        'desired_state' => [
            'barcode' => '868000111222',
            'title' => 'Erkek Cüzdan',
            'attributes' => [
                ['attributeId' => 338, 'attributeValueId' => 10],
            ],
        ],
        'error' => [
            'code' => 'MISSING_ATTRIBUTE',
            'message' => 'Materyal (attributeId: 342) zorunludur.',
        ],
    ]);

    $action = new HealFailedOperation;
    $result = $action($operation);

    expect($result['success'])->toBeTrue()
        ->and($result['repaired'])->toBeTrue()
        ->and($result['extracted_attribute']['value'])->toBe('Hakiki Deri');

    $operation->refresh();
    expect($operation->status)->toBe(SyncState::Pending)
        ->and($operation->error)->toBeNull()
        ->and($operation->remote_result['self_healed'])->toBeTrue()
        ->and($operation->desired_state['attributes'])->toHaveCount(2);

    $product->refresh();
    /** @var array<string, mixed> $attrs */
    $attrs = is_array($product->attributes) ? $product->attributes : [];
    expect($attrs['Materyal'])->toBe('Hakiki Deri');
});

it('triggers self healing through http endpoint', function (): void {
    SelfHealingSyncAgent::fake([
        [
            'repaired' => true,
            'error_code' => 'INVALID_FORMAT',
            'root_cause' => 'Beden değeri formatı geçersizdi.',
            'missing_attribute_name' => 'Beden',
            'extracted_value' => 'Standart',
            'repaired_payload' => ['size' => 'Standart'],
            'repair_summary' => 'Format düzeltildi.',
        ],
    ]);

    $user = User::factory()->create()->assignRole('Yönetici');
    $connection = ChannelConnection::factory()->create();
    $operation = ChannelOperation::factory()->create([
        'connection_id' => $connection->id,
        'entity_type' => 'product',
        'entity_id' => 1,
        'operation' => OperationType::ProductCreate->value,
        'status' => SyncState::Failed,
        'desired_state' => ['barcode' => '123'],
        'error' => ['message' => 'Format hatası'],
    ]);

    $response = $this->actingAs($user)->postJson(route('ai.channels.heal', $operation));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('repaired', true);
});
