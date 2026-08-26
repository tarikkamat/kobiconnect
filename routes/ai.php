<?php

declare(strict_types=1);

use App\Listeners\ConfigureTenantHost;
use App\Mcp\Servers\KobiConnectServer;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;
use Laravel\Mcp\Server\Registrar;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| MCP Sunucusu
|--------------------------------------------------------------------------
|
| Kimlik OAuth 2.1 ile: istemci POST /{tenant}/mcp'ye kimliksiz gelir, 401 ve
| WWW-Authenticate ile asagidaki kesif belgelerine yonlendirilir, tarayicida
| panel girisini yapar ve Bearer token alir.
|
| HER TENANT KENDI YETKILENDIRME SUNUCUSUDUR (issuer: /{tenant}). Passport'un
| route'lari da `{tenant}/oauth/...` altinda yasar ve oauth_* tablolari tenant
| semasindadir — bkz. config/passport.php. Central'da kullanici olmadigi icin
| tek merkezi bir sunucu token'i hangi tenant'in kullanicisina yazacagini
| bilemezdi.
|
*/

// `web` yok: kimlik cerezden degil Bearer token'dan gelir, dolayisiyla ne
// oturuma ne CSRF'e ihtiyac var. Tenancy yine de sart — `auth:api` token'in
// kullanicisini TENANT semasinda arar. `verified` panelle esitlik icin:
// MCP panelin yapabildigi her seyi yapar, ayni kapiya tabi olmali.
Mcp::web('{tenant}/mcp', KobiConnectServer::class)
    ->middleware([
        InitializeTenancyByPath::class,
        ConfigureTenantHost::class,
        'auth:api',
        'verified',
    ]);

// `mcp:use` kapsamini tanimlar; Passport kurulu degilse sessizce hicbir sey
// yapmaz. Kesif belgeleri bu kapsami ilan eder.
Registrar::ensureMcpScope();

/*
|--------------------------------------------------------------------------
| OAuth Kesif Belgeleri (RFC 9728 / RFC 8414)
|--------------------------------------------------------------------------
|
| Mcp::oauthRoutes() BILEREK cagrilmiyor: tek bir yetkilendirme sunucusu
| varsayar ve `authorization_servers` degerini url('/') olarak sabitler. Bize
| tenant basina bir issuer lazim, o yuzden ayni dort ucu burada uretiyoruz.
| Route ADLARI paketinkiyle ayni tutuldu — AddWwwAuthenticateHeader 401
| yanitina koydugu resource_metadata baglantisini o adla cozer.
|
*/

Route::get('/.well-known/oauth-protected-resource/{path}', function (string $path): JsonResponse {
    $tenant = Str::before($path, '/');

    abort_unless(Tenant::query()->whereKey($tenant)->exists(), 404);

    return response()->json([
        'resource' => url('/'.$path),
        'authorization_servers' => [url('/'.$tenant)],
        'scopes_supported' => [Registrar::OAUTH_SCOPE],
    ]);
})->where('path', '.*')->name('mcp.oauth.protected-resource.nested');

// Ayni belge iki adresten sunulur: RFC 8414 issuer yolunu host'tan SONRA
// ekler (/.well-known/...&/1005), OIDC alisikligiyla gelen istemciler ise
// issuer'in ardina ekler. Hangi istemcinin hangisini denedigine bagli
// kalmamak icin ikisi de var.
$authorizationServerMetadata = function (string $tenant): JsonResponse {
    abort_unless(Tenant::query()->whereKey($tenant)->exists(), 404);

    $issuer = url('/'.$tenant);

    return response()->json([
        'issuer' => $issuer,
        'authorization_endpoint' => route('passport.authorizations.authorize', ['tenant' => $tenant]),
        'token_endpoint' => route('passport.token', ['tenant' => $tenant]),
        'registration_endpoint' => route('mcp.oauth.register', ['tenant' => $tenant]),
        'response_types_supported' => ['code'],
        'code_challenge_methods_supported' => ['S256'],
        'scopes_supported' => [Registrar::OAUTH_SCOPE],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
    ]);
};

Route::get('/.well-known/oauth-authorization-server/{tenant}', $authorizationServerMetadata)
    ->name('mcp.oauth.authorization-server.nested');

Route::get('/{tenant}/.well-known/oauth-authorization-server', $authorizationServerMetadata)
    ->name('mcp.oauth.authorization-server.tenant');

/*
|--------------------------------------------------------------------------
| Dinamik Istemci Kaydi (RFC 7591)
|--------------------------------------------------------------------------
|
| Claude'un "Connect" akisi kendi istemcisini burada kaydeder; elle client id
| uretmek gerekmez. Kayit tenant semasindaki `oauth_clients` tablosuna gider,
| bu yuzden tenancy middleware'i sart.
|
*/

Route::post('{tenant}/oauth/register', OAuthRegisterController::class)
    ->middleware([InitializeTenancyByPath::class, ConfigureTenantHost::class])
    ->name('mcp.oauth.register');
