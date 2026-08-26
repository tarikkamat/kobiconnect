<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;
use Tests\TestCase;

beforeEach(function (): void {
    $this->tenantId = TestCase::TENANT_ID;
});

it('kimliksiz cagriyi 401 ile ve kesif adresini soyleyerek reddeder', function (): void {
    $response = $this->post("/{$this->tenantId}/mcp", [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ], ['Accept' => 'application/json, text/event-stream']);

    $response->assertUnauthorized();

    expect($response->headers->get('WWW-Authenticate'))
        ->toContain('resource_metadata=')
        // Istemci once BU adresi okur; tenant'i tasimazsa yanlis
        // yetkilendirme sunucusuna gider.
        ->toContain("/.well-known/oauth-protected-resource/{$this->tenantId}/mcp");
});

it('korunan kaynak belgesi tenant\'in kendi yetkilendirme sunucusunu gosterir', function (): void {
    $this->getJson("/.well-known/oauth-protected-resource/{$this->tenantId}/mcp")
        ->assertOk()
        ->assertJson([
            'resource' => url("/{$this->tenantId}/mcp"),
            'authorization_servers' => [url("/{$this->tenantId}")],
            'scopes_supported' => [Registrar::OAUTH_SCOPE],
        ]);
});

it('yetkilendirme sunucusu belgesi tenant onekli uclari yayinlar', function (): void {
    $this->getJson("/.well-known/oauth-authorization-server/{$this->tenantId}")
        ->assertOk()
        ->assertJson([
            'issuer' => url("/{$this->tenantId}"),
            'authorization_endpoint' => url("/{$this->tenantId}/oauth/authorize"),
            'token_endpoint' => url("/{$this->tenantId}/oauth/token"),
            'registration_endpoint' => url("/{$this->tenantId}/oauth/register"),
            'code_challenge_methods_supported' => ['S256'],
        ]);
});

it('ayni belge issuer\'in altindan da sunulur', function (): void {
    $this->getJson("/{$this->tenantId}/.well-known/oauth-authorization-server")
        ->assertOk()
        ->assertJson(['issuer' => url("/{$this->tenantId}")]);
});

it('bilinmeyen tenant icin kesif belgesi yoktur', function (): void {
    $this->getJson('/.well-known/oauth-authorization-server/yok-boyle-tenant')->assertNotFound();
    $this->getJson('/.well-known/oauth-protected-resource/yok-boyle-tenant/mcp')->assertNotFound();
});

it('dinamik istemci kaydi tenant semasina yazar', function (): void {
    $response = $this->postJson("/{$this->tenantId}/oauth/register", [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ]);

    $response->assertCreated();

    $clientId = $response->json('client_id');

    expect($clientId)->not->toBeEmpty();

    // Kayit tenant semasinda yasamali: central'da oauth_clients tablosu bile
    // yoktur, olsaydi baska bir tenant'in istemcisi burada gorunurdu.
    expect(DB::connection('tenant')->table('oauth_clients')->where('id', $clientId)->exists())->toBeTrue();
});

it('onay ekrani once panel girisini ister', function (): void {
    $clientId = $this->postJson("/{$this->tenantId}/oauth/register", [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->json('client_id');

    // Claude'un tarayicida acacagi adres. Oturum yoksa kullanici once panele
    // giris yapar — kullanicinin gordugu akis tam olarak budur.
    $this->get("/{$this->tenantId}/oauth/authorize?".http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
        'response_type' => 'code',
        'scope' => Registrar::OAUTH_SCOPE,
        'code_challenge' => str_repeat('a', 43),
        'code_challenge_method' => 'S256',
    ]))->assertRedirect(route('login'));
});

it('giris yapmis kullaniciya onay ekranini gosterir', function (): void {
    $clientId = $this->postJson("/{$this->tenantId}/oauth/register", [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
    ])->json('client_id');

    $this->actingAs(User::factory()->create())
        ->get("/{$this->tenantId}/oauth/authorize?".http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
            'response_type' => 'code',
            'scope' => Registrar::OAUTH_SCOPE,
            'code_challenge' => str_repeat('a', 43),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSee('Claude');
});

it('gecerli token ile MCP araclari calisir', function (): void {
    $user = User::factory()->create();

    Passport::actingAs($user, [Registrar::OAUTH_SCOPE], 'api');

    $this->postJson("/{$this->tenantId}/mcp", [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ], ['Accept' => 'application/json, text/event-stream'])
        ->assertOk()
        ->assertSee('list-actions');
});

it('token dogrulanmamis e-postayi gecemez', function (): void {
    $user = User::factory()->unverified()->create();

    Passport::actingAs($user, [Registrar::OAUTH_SCOPE], 'api');

    $this->postJson("/{$this->tenantId}/mcp", [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ], ['Accept' => 'application/json, text/event-stream'])
        ->assertForbidden();
});
