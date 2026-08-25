<?php

declare(strict_types=1);

use App\Mcp\ActionCatalog;
use App\Mcp\Servers\KobiConnectServer;
use Laravel\Mcp\Facades\Mcp;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Tests\TestCase;

uses(TestCase::class);

it('katalogda panelin her modulunden action bulunur', function (): void {
    $actions = ActionCatalog::all();

    expect(array_keys($actions))
        ->toContain('products.index', 'orders.index', 'stock.update', 'connections.store')
        ->toContain('claims.index', 'reports.channels', 'warehouses.store', 'team.index')
        ->toContain('ai.copilot.chat', 'ai.pricing.dynamic-price', 'mapping.show');

    expect($actions['stock.update']['method'])->toBe('PATCH')
        ->and($actions['stock.update']['path'])->toBe('inventory/stock/{variant}/{warehouse}');
});

it('hesap guvenligi uclarini disarida birakir', function (): void {
    expect(array_keys(ActionCatalog::all()))
        ->not->toContain('user-password.update', 'security.edit', 'profile.destroy');
});

it('tenant disi route\'lari almaz', function (): void {
    expect(array_keys(ActionCatalog::all()))->not->toContain('central.login', 'onboarding.register');
});

it('aciklamayi controller docblock\'undan turetir', function (): void {
    expect(ActionCatalog::all()['orders.index']['description'])->toContain('Sipariş listesi');
});

it('FormRequest kullanan uclarda alanlari kurallariyla birlikte tarifler', function (): void {
    $described = ActionCatalog::describe('stock.update');

    expect($described['path_parameters'])->toBe(['variant', 'warehouse'])
        ->and($described['input']['on_hand'])->toContain('integer')
        ->and($described['input']['reason'])->toContain('required_with:on_hand')
        ->and($described['labels']['on_hand'])->toBe('eldeki stok');
});

it('FormRequest yoksa alan listesi yerine not birakir', function (): void {
    $described = ActionCatalog::describe('orders.index');

    expect($described['input'])->toBe([])
        ->and($described['note'])->toContain('FormRequest');
});

it('bilinmeyen action reddedilir', function (): void {
    ActionCatalog::describe('nope.nope');
})->throws(InvalidArgumentException::class);

it('MCP sunucusu tenant yolunda ve oturum arkasinda kayitli', function (): void {
    $route = Mcp::getWebServer('{tenant}/mcp');

    expect($route)->not->toBeNull()
        ->and($route->getAction('uses'))->not->toBeNull()
        ->and($route->middleware())->toContain('auth', 'verified')
        ->and($route->middleware())->toContain(InitializeTenancyByPath::class);
});

it('sunucu uc araci yayinlar', function (): void {
    expect((new ReflectionClass(KobiConnectServer::class))->getDefaultProperties()['tools'])
        ->toHaveCount(3);
});
