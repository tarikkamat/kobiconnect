<?php

declare(strict_types=1);

use App\Mcp\ActionCatalog;
use App\Mcp\Servers\KobiConnectServer;
use App\Mcp\Tools\CallActionTool;
use App\Mcp\Tools\ListActionsTool;
use App\Models\InventoryItem;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\TenantRoleSeeder;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    $this->seed(TenantRoleSeeder::class);

    $this->keeper = User::factory()->create()->assignRole('Depo');
    $this->warehouse = Warehouse::factory()->create(['is_default' => true]);
    $this->variant = ProductVariant::factory()->create(['sku' => 'SKU-1']);
});

it('okuma action\'i ekran verisini dondurur', function (): void {
    InventoryItem::factory()->create([
        'variant_id' => $this->variant->id,
        'warehouse_id' => $this->warehouse->id,
        'on_hand' => 12,
    ]);

    $this->actingAs($this->keeper);

    $result = ActionCatalog::call('stock.index', []);

    expect($result['ok'])->toBeTrue()
        ->and($result['screen'])->toBe('inventory/stock/index')
        ->and($result['data']['variants']['data'][0]['sku'])->toBe('SKU-1');
});

it('yazma action\'i kaydi degistirir', function (): void {
    Log::spy();

    $this->actingAs($this->keeper);

    $result = ActionCatalog::call('stock.update', [
        'variant' => $this->variant->id,
        'warehouse' => $this->warehouse->id,
        'on_hand' => 40,
        'reason' => 'sayım',
    ]);

    expect($result)->toMatchArray(['ok' => true]);

    expect(InventoryItem::query()
        ->where('variant_id', $this->variant->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->value('on_hand'))->toBe(40);
});

it('dogrulama hatasini alan adlariyla dondurur', function (): void {
    $this->actingAs($this->keeper);

    $result = ActionCatalog::call('stock.update', [
        'variant' => $this->variant->id,
        'warehouse' => $this->warehouse->id,
        'on_hand' => 40,
    ]);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toBe('validation')
        ->and($result['errors'])->toHaveKey('reason');
});

it('yetkisiz kullaniciya forbidden doner', function (): void {
    $accountant = User::factory()->create()->assignRole('Muhasebe');

    $this->actingAs($accountant);

    $result = ActionCatalog::call('stock.update', [
        'variant' => $this->variant->id,
        'warehouse' => $this->warehouse->id,
        'on_hand' => 40,
        'reason' => 'sayım',
    ]);

    expect($result['ok'])->toBeFalse()->and($result['error'])->toBe('forbidden');
});

it('ic istek disaridaki istegi bozmaz', function (): void {
    $this->actingAs($this->keeper);

    $outer = request();

    ActionCatalog::call('stock.index', []);

    expect(app('request'))->toBe($outer);
});

it('MCP ucu kimliksiz cagriyi reddeder', function (): void {
    $this->post('/'.tenant('id').'/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ], ['Accept' => 'application/json, text/event-stream'])->assertUnauthorized();
});

it('call-action araci ekran verisini dondurur', function (): void {
    KobiConnectServer::actingAs($this->keeper)
        ->tool(CallActionTool::class, ['action' => 'stock.index'])
        ->assertOk()
        ->assertSee('inventory/stock/index');
});

it('list-actions araci katalogu dondurur', function (): void {
    KobiConnectServer::actingAs($this->keeper)
        ->tool(ListActionsTool::class, ['search' => 'stok'])
        ->assertOk()
        ->assertSee('stock.index');
});

it('debug', function (): void {
    $this->actingAs($this->keeper);
    $route = Illuminate\Support\Facades\Route::getRoutes()->getByName('stock.update');
    $url = route('stock.update', ['variant' => $this->variant->id, 'warehouse' => $this->warehouse->id]);
    dump($url);
    $req = Illuminate\Http\Request::create($url, 'PATCH', ['on_hand' => 40, 'reason' => 'x']);
    $route->bind($req);
    dump($route->parameters());
    app(Illuminate\Routing\Router::class)->substituteImplicitBindings($route);
    dump(array_map(fn ($p) => is_object($p) ? get_class($p) : $p, $route->parameters()));
});
