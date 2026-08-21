<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada;

use App\Marketplaces\Hepsiburada\Exceptions\HepsiburadaApiException;
use App\Marketplaces\Support\Exceptions\MarketplaceException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Uri;
use Throwable;

/**
 * The one place that knows how to talk to Hepsiburada.
 *
 * Three things make this different from a normal API client, all measured:
 *
 *  1. There is no base url. The host is chosen per SERVICE (H1) - catalog on
 *     mpop, price/stock on listing-external, orders on oms-external - and
 *     production is the SIT host with `-sit` removed. One credential pair opens
 *     all three.
 *  2. `success` is not universal (H2). A catalog call can answer HTTP 200 with
 *     `{"success": false, "code": 1003}`, a routing mistake answers the Spring
 *     envelope, and listing/order/package answers carry no envelope at all. So
 *     every response is parsed against BOTH failure shapes, and no shared
 *     `unwrap()` exists - the caller knows which envelope its endpoint uses.
 *  3. There is no idempotency key anywhere (H12), so retrying is ASYMMETRIC:
 *     a read may be retried on 429/5xx/timeout, a write only on 429. A 429 is
 *     refused before processing so replaying it is safe; an ambiguous 5xx or
 *     timeout on `products/import` would duplicate a real catalog write, and
 *     the documented recovery for that is `trackingId-history`, not a replay.
 */
final class HepsiburadaClient
{
    public function __construct(
        private readonly HepsiburadaRateLimiter $limiter,
        private readonly Repository $config,
        private readonly ?HepsiburadaCredentials $credentials = null,
    ) {}

    public function as(HepsiburadaCredentials $credentials): self
    {
        return new self($this->limiter, $this->config, $credentials);
    }

    public function credentials(): ?HepsiburadaCredentials
    {
        return $this->credentials;
    }

    /**
     * @param  string  $endpoint  the stable operation name, e.g. `getAllCategoriesByParameters`;
     *                            it keys the per endpoint `version` parameter
     * @param  array<string, mixed>  $query
     * @return array<array-key, mixed>
     *
     * @throws HepsiburadaApiException
     */
    public function get(HepsiburadaService $service, string $endpoint, string $path, array $query = []): array
    {
        return $this->send($service, $endpoint, $path, write: false, send: fn (PendingRequest $request, string $url): Response => $request->get($url, $query));
    }

    /**
     * Every write here is asynchronous: the answer is a `trackingId` or an
     * `Id`, never an entity and never an item level result (H4).
     *
     * @param  list<array<string, mixed>>|array<string, mixed>  $body
     * @return array<array-key, mixed>
     *
     * @throws HepsiburadaApiException
     */
    public function post(HepsiburadaService $service, string $endpoint, string $path, array $body): array
    {
        return $this->send($service, $endpoint, $path, write: true, send: fn (PendingRequest $request, string $url): Response => $request->post($url, $body));
    }

    /**
     * `POST /products/import` refuses a plain JSON body with code 3002: it
     * wants `multipart/form-data` carrying a part literally named `file` whose
     * content is a `.json` document (§4.1.4).
     *
     * The row order of `$rows` is load bearing - `itemOrderID` in the poll
     * result is the index into exactly this array (H5) - so it is encoded
     * as given and never re-keyed.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<array-key, mixed>
     *
     * @throws HepsiburadaApiException
     */
    public function upload(HepsiburadaService $service, string $endpoint, string $path, array $rows, string $filename = 'integrator.json'): array
    {
        $document = json_encode(($rows), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($document === false) {
            throw new MarketplaceException("Hepsiburada [{$endpoint}] payload could not be encoded as JSON.");
        }

        return $this->send(
            $service,
            $endpoint,
            $path,
            write: true,
            send: fn (PendingRequest $request, string $url): Response => $request
                ->attach('file', $document, $filename, ['Content-Type' => 'application/json'])
                ->post($url),
        );
    }

    /**
     * @param  callable(PendingRequest, string): Response  $send
     * @return array<array-key, mixed>
     *
     * @throws HepsiburadaApiException
     */
    private function send(HepsiburadaService $service, string $endpoint, string $path, bool $write, callable $send): array
    {
        $credentials = $this->credentials ?? throw new MarketplaceException(
            'No Hepsiburada credentials bound; call HepsiburadaClient::as() with the connection credentials first.'
        );

        $this->limiter->acquire($service);

        $request = Http::baseUrl($this->baseUrl($service, $credentials))
            ->withBasicAuth($credentials->merchantId, $credentials->serviceKey)
            ->withHeaders($this->headers($credentials))
            ->timeout($this->integer('timeout', 30))
            ->retry(
                times: $this->integer('retry.times', 3),
                sleepMilliseconds: fn (int $attempt, Throwable $exception): int => $this->backoffMilliseconds($attempt, $exception),
                when: fn (Throwable $exception): bool => $this->shouldRetry($exception, $write),
                throw: false,
            );

        $response = $send($request, $this->url($endpoint, $path));

        if ($response->failed()) {
            throw HepsiburadaApiException::fromResponse($endpoint, $response);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return [];
        }

        // Envelope A: business failure wearing an HTTP 200 (§8.1).
        if (($payload['success'] ?? null) === false) {
            throw HepsiburadaApiException::fromBusinessEnvelope($endpoint, $response->status(), $payload, $response->body());
        }

        // Envelope B: a Spring error body that somehow arrived with a 2xx.
        if (isset($payload['timestamp'], $payload['error'], $payload['path']) && is_numeric($payload['status'] ?? null) && (int) $payload['status'] >= 400) {
            throw HepsiburadaApiException::fromBusinessEnvelope($endpoint, (int) $payload['status'], $payload, $response->body());
        }

        return $payload;
    }

    /**
     * `version` is a query parameter whose right value differs PER ENDPOINT -
     * 1 on most catalog calls, 2 on `trackingId-history`, 5 observed on the
     * attribute value endpoint (§4.0). A single global constant is guaranteed
     * to be wrong somewhere, so the map lives in config and an endpoint that
     * names none simply sends none.
     */
    private function url(string $endpoint, string $path): string
    {
        $version = $this->config->get("marketplaces.hepsiburada.versions.{$endpoint}");
        $path = ltrim($path, '/');

        return is_numeric($version)
            ? (string) Uri::of($path)->withQuery(['version' => (int) $version])
            : $path;
    }

    /**
     * The User-Agent is the second half of the credential, not decoration:
     * without it - or with the Trendyol style `"{id} - {Name}"` template -
     * Hepsiburada answers 401/403 (§2.1).
     *
     * @return array<string, string>
     */
    private function headers(HepsiburadaCredentials $credentials): array
    {
        $headers = [
            'User-Agent' => $credentials->userAgent(),
            'Accept' => 'application/json',
        ];

        $requestId = Context::get('request_id');

        if (is_string($requestId) && $requestId !== '') {
            $headers['X-Request-Id'] = $requestId;
        }

        return $headers;
    }

    private function baseUrl(HepsiburadaService $service, HepsiburadaCredentials $credentials): string
    {
        $environment = $credentials->sit ? 'sit' : 'production';
        $url = $this->config->get("marketplaces.hepsiburada.base_urls.{$environment}.{$service->value}");

        if (! is_string($url) || $url === '') {
            throw new MarketplaceException(
                "No Hepsiburada base url configured for service [{$service->value}] in environment [{$environment}]."
            );
        }

        return rtrim($url, '/');
    }

    /**
     * Full jitter exponential backoff from our own clock.
     *
     * A 429 is documented to carry `X-RateLimit-Reset` in seconds (§7.3) - it
     * is used when it is there and ignored when it is not, because measured
     * 200s carry no rate limit header at all and `Retry-After` is documented
     * nowhere.
     */
    private function backoffMilliseconds(int $attempt, Throwable $exception): int
    {
        if ($exception instanceof RequestException) {
            $reset = $exception->response->header('X-RateLimit-Reset');

            if (is_numeric($reset) && (float) $reset > 0) {
                return (int) min((float) $reset * 1000, $this->integer('retry.max_delay_ms', 60000));
            }
        }

        $base = $this->integer('retry.base_delay_ms', 1000);
        $cap = $this->integer('retry.max_delay_ms', 60000);

        return random_int(1, max(1, min($cap, $base * 2 ** max(0, $attempt - 1))));
    }

    /**
     * Asymmetric by design (H12, §8.5). There is no idempotency key, so a write
     * replayed after an ambiguous failure duplicates a real catalog mutation.
     */
    private function shouldRetry(Throwable $exception, bool $write): bool
    {
        if ($exception instanceof ConnectionException) {
            return ! $write;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->response->status();

        // 429 is refused before Hepsiburada processes anything, so replaying it
        // is safe even for a write.
        if ($status === 429) {
            return true;
        }

        if ($write) {
            return false;
        }

        $statuses = $this->config->get('marketplaces.hepsiburada.retry.statuses', []);

        return is_array($statuses) && in_array($status, array_map(intval(...), $statuses), true);
    }

    private function integer(string $key, int $default): int
    {
        $value = $this->config->get("marketplaces.hepsiburada.{$key}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
