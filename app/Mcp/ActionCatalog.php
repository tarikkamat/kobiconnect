<?php

declare(strict_types=1);

namespace App\Mcp;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Panelin route tablosunu MCP "action"larina cevirir ve calistirir.
 *
 * ponytail: her ekran icin ayri bir MCP Tool sinifi YAZILMADI. Ozellik zaten
 * controller'da duruyor — yetki (Gate), dogrulama (FormRequest) ve ekran
 * verisi (Inertia prop'lari). Katalog route tablosunu okur, cagri ayni
 * route'u ic istek olarak calistirir; yeni bir ekran eklendiginde MCP kapsami
 * kendiliginden buyur.
 *
 * Tavan: aciklama controller docblock'undan gelir, girdi semasi da yalnizca
 * FormRequest kullanan uclarda dolu. Ajan yanlis action seciyorsa cozum
 * ilgili controller metoduna docblock yazmaktir; alan listesi eksikse ucu
 * FormRequest'e tasimaktir. Elle yazilan bir manifest ikisinden de once
 * bayatlar.
 */
final class ActionCatalog
{
    /**
     * MCP'ye ASLA acilmayan uclar: hesap guvenligi ve arayuz tercihleri.
     * Parola/passkey degistirmek ve hesap silmek bir ajanin isi degil.
     *
     * @var list<string>
     */
    private const array DENIED = [
        'security.edit',
        'user-password.update',
        'profile.destroy',
        'well-known.passkeys',
        'table-columns.update',
    ];

    /**
     * @return array<string, array{action: string, method: string, path: string, description: string}>
     */
    public static function all(): array
    {
        $actions = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($route->uri(), '{tenant}/')) {
                continue;
            }

            if (in_array($name, self::DENIED, true)) {
                continue;
            }

            $verb = self::verb($route);

            if ($verb === null) {
                continue;
            }

            $actions[$name] = [
                'action' => $name,
                'method' => $verb,
                'path' => Str::after($route->uri(), '{tenant}/'),
                'description' => self::description($route),
            ];
        }

        ksort($actions);

        return $actions;
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(string $name): array
    {
        $route = self::route($name);
        $formRequest = self::formRequest($route);

        return [
            ...self::all()[$name],
            'path_parameters' => array_values(array_diff($route->parameterNames(), ['tenant'])),
            'input' => $formRequest === null ? [] : self::rules($formRequest),
            'labels' => $formRequest === null ? [] : self::labels($formRequest),
            'note' => $formRequest === null
                ? 'Girdi alanlari bu ucta FormRequest ile tanimli degil; call-action cagrisi dogrulama hatasini alan adlariyla birlikte dondurur.'
                : null,
        ];
    }

    /**
     * Action'i ic istek olarak calistirir.
     *
     * Middleware BILEREK atlanir: tenancy, oturum ve yetkilendirme MCP
     * route'unun kendi yiginin da zaten kuruldu. Ikinci kez calistirmak yeni
     * bir oturum baslatip cagiran kullaniciyi kaybederdi.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public static function call(string $name, array $arguments): array
    {
        $route = self::route($name);
        $verb = self::verb($route) ?? 'GET';

        $pathParameters = array_intersect_key(
            $arguments,
            array_flip(array_diff($route->parameterNames(), ['tenant'])),
        );

        $payload = array_diff_key($arguments, $pathParameters);
        $url = route($name, $pathParameters);

        $request = Request::create($url, $verb, $payload);
        $request->headers->set('X-Inertia', 'true');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => request()->user());

        return self::run($route, $request);
    }

    /**
     * @return array<string, mixed>
     */
    private static function run(RoutingRoute $route, Request $request): array
    {
        $router = app(Router::class);
        $previous = app('request');

        $request->setRouteResolver(fn (): RoutingRoute => $route);
        app()->instance('request', $request);

        try {
            $route->bind($request);

            // PathTenantResolver'in yaptigi: tenant segmenti bir controller
            // argumani degildir, cozuldukten sonra dusurulur. Kalirsa
            // ControllerDispatcher onu ilk model parametresine denk getirir.
            $route->forgetParameter('tenant');

            $router->substituteBindings($route);
            $router->substituteImplicitBindings($route);

            return self::present($router->prepareResponse($request, $route->run()));
        } catch (ValidationException $e) {
            return ['ok' => false, 'error' => 'validation', 'errors' => $e->errors()];
        } catch (AuthorizationException $e) {
            return ['ok' => false, 'error' => 'forbidden', 'message' => $e->getMessage()];
        } catch (ModelNotFoundException|NotFoundHttpException) {
            return ['ok' => false, 'error' => 'not_found'];
        } catch (HttpExceptionInterface $e) {
            return ['ok' => false, 'error' => 'http', 'status' => $e->getStatusCode(), 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()];
        } finally {
            app()->instance('request', $previous);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function present(SymfonyResponse $response): array
    {
        if ($response instanceof RedirectResponse) {
            return ['ok' => true, 'status' => $response->getStatusCode(), 'redirect' => $response->getTargetUrl()];
        }

        $content = (string) $response->getContent();
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return ['ok' => true, 'status' => $response->getStatusCode(), 'body' => Str::limit($content, 20000)];
        }

        // Inertia sayfasi: ekranin verisi `props` icindedir, gerisi istemci metadatasi.
        if (isset($decoded['component'], $decoded['props'])) {
            return ['ok' => true, 'screen' => $decoded['component'], 'data' => $decoded['props']];
        }

        return ['ok' => true, 'status' => $response->getStatusCode(), 'data' => $decoded];
    }

    private static function route(string $name): RoutingRoute
    {
        if (! array_key_exists($name, self::all())) {
            throw new \InvalidArgumentException("Bilinmeyen action: {$name}");
        }

        $route = Route::getRoutes()->getByName($name);

        assert($route instanceof RoutingRoute);

        return $route;
    }

    private static function verb(RoutingRoute $route): ?string
    {
        $verbs = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));

        return count($verbs) === 1 ? $verbs[0] : null;
    }

    private static function description(RoutingRoute $route): string
    {
        $method = self::controllerMethod($route);

        if ($method === null) {
            return '';
        }

        return self::summary($method->getDocComment() ?: '')
            ?: self::summary($method->getDeclaringClass()->getDocComment() ?: '');
    }

    private static function summary(string $docBlock): string
    {
        $lines = [];

        foreach (preg_split('/\R/', $docBlock) ?: [] as $line) {
            $line = trim(preg_replace('#^\s*(/\*\*|\*/|\*)\s?#', '', $line) ?? '');

            if (str_starts_with($line, '@')) {
                break;
            }

            if ($line === '') {
                if ($lines !== []) {
                    break;
                }

                continue;
            }

            $lines[] = $line;
        }

        return implode(' ', $lines);
    }

    private static function controllerMethod(RoutingRoute $route): ?ReflectionMethod
    {
        $action = $route->getAction('uses');

        if (! is_string($action)) {
            return null;
        }

        [$class, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');

        return method_exists($class, $method) ? new ReflectionMethod($class, $method) : null;
    }

    private static function formRequest(RoutingRoute $route): ?FormRequest
    {
        $method = self::controllerMethod($route);

        foreach ($method?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            if (is_subclass_of($type->getName(), FormRequest::class)) {
                /** @var FormRequest $instance */
                $instance = new ($type->getName());

                return $instance;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function rules(FormRequest $request): array
    {
        if (! method_exists($request, 'rules')) {
            return [];
        }

        try {
            /** @var array<string, ValidationRule|array<mixed>|string> $rules */
            $rules = $request->rules();
        } catch (Throwable) {
            // Kimi kural kumesi istek baglami ister (bkz. ConnectionRequest);
            // sema uretmek ugruna 500 vermeyiz.
            return [];
        }

        return array_map(
            fn (mixed $rule): string => implode('|', array_map(self::stringifyRule(...), is_array($rule) ? $rule : [$rule])),
            $rules,
        );
    }

    private static function stringifyRule(mixed $rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }

        return is_object($rule) && method_exists($rule, '__toString')
            ? (string) $rule
            : Str::snake(class_basename($rule));
    }

    /**
     * @return array<string, string>
     */
    private static function labels(FormRequest $request): array
    {
        try {
            return $request->attributes();
        } catch (Throwable) {
            return [];
        }
    }
}
