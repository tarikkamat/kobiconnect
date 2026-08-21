<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * stancl/tenancy v3.10'da sifir Octane farkindaligi vardir: `Tenancy` bir
 * singleton'dir ve HTTP istegi sonunda `tenancy()->end()` cagiran hicbir sey
 * yoktur. RoadRunner/Octane altinda tenant A ile biten bir istekten sonra ayni
 * worker'a gelen *central* istek hala tenant A context'inde calisir.
 *
 * Bu terminating middleware o sizintiyi kapatir. BACKEND-PLAN.md §2.3.
 */
final class EndTenancyAfterRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $tenancy = app(Tenancy::class);

        if ($tenancy->initialized) {
            $tenancy->end();
        }
    }
}
