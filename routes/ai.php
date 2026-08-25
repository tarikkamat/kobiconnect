<?php

declare(strict_types=1);

use App\Listeners\ConfigureTenantHost;
use App\Mcp\Servers\KobiConnectServer;
use Laravel\Mcp\Facades\Mcp;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\ScopeSessions;

/*
|--------------------------------------------------------------------------
| MCP Sunucusu
|--------------------------------------------------------------------------
|
| Uc, panelin kendisiyle ayni yigin uzerinde yasar: tenant path'ten cozulur
| (POST /{tenant}/mcp) ve kimlik `web` oturumundan gelir. Boylece MCP cagrisi
| operatorun kendi rolleriyle sinirli kalir — ayri bir yetki modeli yok.
|
| ponytail: token/OAuth kimligi EKLENMEDI. Bugun sunucuya oturum cerezi
| tasiyabilen istemciler baglanir. Claude Desktop gibi cerezsiz bir istemci
| baglanacaksa buraya Sanctum personal access token'i girer.
|
*/

Mcp::web('{tenant}/mcp', KobiConnectServer::class)
    ->middleware([
        'web',
        InitializeTenancyByPath::class,
        ScopeSessions::class,
        ConfigureTenantHost::class,
        'auth',
        'verified',
    ]);
