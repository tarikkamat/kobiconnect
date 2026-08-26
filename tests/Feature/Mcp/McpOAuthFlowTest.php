<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Laravel\Mcp\Server\Registrar;
use Tests\TestCase;

/**
 * Tam yetkilendirme kodu + PKCE akisi, Claude'un yaptigi sirayla.
 *
 * Bu dosyanin asil derdi son iki assertion: bir tenant icin uretilen token
 * baska bir tenant'ta CALISMAMALI. Kapsam kararinin (issuer basina tenant,
 * oauth_* tablolari tenant semasinda) tek kaniti budur.
 */
it('istemci kaydindan bearer token\'a kadar akis calisir ve token tenant\'ina hapistir', function (): void {
    $verifier = str_repeat('a', 64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $redirectUri = 'https://claude.ai/api/mcp/auth_callback';

    $tenantId = TestCase::TENANT_ID;

    // 1. Dinamik istemci kaydi (RFC 7591) — Claude kendi client_id'sini alir.
    $clientId = $this->postJson("/{$tenantId}/oauth/register", [
        'client_name' => 'Claude',
        'redirect_uris' => [$redirectUri],
    ])->assertCreated()->json('client_id');

    // 2. Kullanici tarayicida onaylar.
    $query = http_build_query([
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => Registrar::OAUTH_SCOPE,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ]);

    $this->actingAs(User::factory()->create())
        ->get("/{$tenantId}/oauth/authorize?".$query)
        ->assertOk();

    // Onay formundaki gizli alan; Passport POST'u bununla oturumdaki
    // yetkilendirme istegine baglar.
    $approval = $this->post("/{$tenantId}/oauth/authorize?".$query, [
        'auth_token' => session('authToken'),
    ]);

    $approval->assertRedirect();

    parse_str((string) parse_url((string) $approval->headers->get('Location'), PHP_URL_QUERY), $callback);

    expect($callback)->toHaveKey('code');

    // 3. Kodun token ile takasi. Public istemci: secret yok, PKCE var.
    $token = $this->postJson("/{$tenantId}/oauth/token", [
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'code_verifier' => $verifier,
        'code' => $callback['code'],
    ])->assertOk()->json('access_token');

    expect($token)->not->toBeEmpty();

    // 4. Token kendi tenant'inda calisir.
    $this->withToken($token)
        ->postJson("/{$tenantId}/mcp", ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'],
            ['Accept' => 'application/json, text/event-stream'])
        ->assertOk()
        ->assertSee('list-actions');

    // 5. ...ve baska tenant'ta calismaz: istemci de token da o semada yoktur.
    // RefreshDatabase `tenants` tablosunu bosaltir ama semayi birakir; ikinci
    // tenant her kosuda sifirdan kurulmali (bkz. Tests\TestCase).
    DB::connection('central')->statement('DROP SCHEMA IF EXISTS "tenantother" CASCADE');

    $other = Tenant::create(['id' => 'other']);

    try {
        // Guard, cozdugu kullaniciyi ornekte tutar ve test surecinde container
        // istekler arasi ayakta kalir. Uretimde bu durum olusmaz: Octane her
        // istekte forgetGuards() cagirir (FlushAuthenticationState). Burada onu
        // elle yapiyoruz, yoksa test ikinci tenant'i hic sorgulamadan gecerdi.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->postJson("/{$other->getTenantKey()}/mcp", ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'],
                ['Accept' => 'application/json, text/event-stream'])
            ->assertUnauthorized();
    } finally {
        // Sema surece bagli yasar; birakirsak ayni id'yi kuran diger testler
        // "database already exists" ile patlar (bkz. PasskeyRelyingPartyTest).
        tenancy()->initialize($this->tenant);
        $other->delete();
    }
});
