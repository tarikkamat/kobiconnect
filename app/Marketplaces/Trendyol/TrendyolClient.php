<?php

namespace App\Marketplaces\Trendyol;

use App\Marketplaces\Support\Exceptions\MarketplaceException;
use App\Marketplaces\Trendyol\Exceptions\TrendyolApiException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The one place that knows how to talk to Trendyol: base URL, Basic auth, the
 * mandatory User-Agent, the request id header, the local rate limit budget,
 * exponential backoff and the three error envelopes.
 *
 * Credentials are per connection, not per application, so the container
 * resolves this class without any and callers bind them with `as()`.
 */
final class TrendyolClient
{
    public function __construct(
        private readonly TrendyolRateLimiter $limiter,
        private readonly Repository $config,
        private readonly ?TrendyolCredentials $credentials = null,
    ) {}

    public function as(TrendyolCredentials $credentials): self
    {
        return new self($this->limiter, $this->config, $credentials);
    }

    public function credentials(): ?TrendyolCredentials
    {
        return $this->credentials;
    }

    /**
     * @param  string  $endpoint  the stable endpoint name, e.g. `getBrands`; it keys both
     *                            the 50/10s bucket and the service group budget
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     * @return array<array-key, mixed>
     *
     * @throws TrendyolApiException
     */
    public function get(string $endpoint, string $path, array $query = [], array $headers = []): array
    {
        return $this->send($endpoint, 'get', $path, $query, $headers);
    }

    /**
     * Mutations here are asynchronous: a 200 carries a `batchRequestId` and
     * says nothing about whether the items were applied (TRENDYOL.md K1).
     *
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array<array-key, mixed>
     *
     * @throws TrendyolApiException
     */
    public function post(string $endpoint, string $path, array $body = [], array $headers = []): array
    {
        return $this->send($endpoint, 'post', $path, $body, $headers);
    }

    /**
     * @param  array<string, mixed>  $data  query string for a read, JSON body for a write
     * @param  array<string, string>  $headers
     * @return array<array-key, mixed>
     *
     * @throws TrendyolApiException
     */
    private function send(string $endpoint, string $method, string $path, array $data, array $headers): array
    {
        $credentials = $this->credentials ?? throw new MarketplaceException(
            'No Trendyol credentials bound; call TrendyolClient::as() with the connection credentials first.'
        );

        $this->limiter->acquire($credentials->sellerId, $endpoint, $credentials->listingTier);

        $response = Http::baseUrl($this->baseUrl($credentials))
            ->withBasicAuth($credentials->apiKey, $credentials->apiSecret)
            ->withHeaders(array_merge($headers, $this->headers($credentials)))
            ->timeout($this->integer('timeout', 30))
            ->retry(
                times: $this->integer('retry.times', 3),
                sleepMilliseconds: fn (int $attempt): int => $this->backoffMilliseconds($attempt),
                when: fn (Throwable $exception): bool => $this->shouldRetry($exception, $credentials),
                throw: false,
            )
            ->{$method}(ltrim($path, '/'), $data);

        if ($response->failed()) {
            throw TrendyolApiException::fromResponse($endpoint, $response);
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    /**
     * The User-Agent is not optional: without it Trendyol answers 403
     * (TRENDYOL.md 2.3). The request id is carried through so one identifier
     * ties a customer complaint to an outgoing marketplace call.
     *
     * @return array<string, string>
     */
    private function headers(TrendyolCredentials $credentials): array
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

    private function baseUrl(TrendyolCredentials $credentials): string
    {
        $key = $credentials->stage ? 'stage' : 'production';
        $url = $this->config->get("marketplaces.trendyol.base_urls.{$key}");

        if (! is_string($url) || $url === '') {
            throw new MarketplaceException("No Trendyol base url configured for environment [{$key}].");
        }

        return rtrim($url, '/');
    }

    /**
     * Full jitter exponential backoff, base 1s capped at 60s (TRENDYOL.md 8.5).
     * Nothing is read from the response: Trendyol publishes no `Retry-After`.
     */
    private function backoffMilliseconds(int $attempt): int
    {
        $base = $this->integer('retry.base_delay_ms', 1000);
        $cap = $this->integer('retry.max_delay_ms', 60000);

        return random_int(1, max(1, min($cap, $base * 2 ** max(0, $attempt - 1))));
    }

    private function shouldRetry(Throwable $exception, TrendyolCredentials $credentials): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        $status = $exception->response->status();

        // On stage a 503 means our egress IP is not on Trendyol's allow list -
        // a configuration error that no amount of retrying fixes (TRENDYOL.md 2.8, 8.5).
        if ($status === 503) {
            return ! $credentials->stage;
        }

        $statuses = $this->config->get('marketplaces.trendyol.retry.statuses', []);

        return is_array($statuses) && in_array($status, array_map(intval(...), $statuses), true);
    }

    private function integer(string $key, int $default): int
    {
        $value = $this->config->get("marketplaces.trendyol.{$key}", $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
