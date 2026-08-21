<?php

declare(strict_types=1);

namespace App\Marketplaces\Hepsiburada\Exceptions;

use App\Marketplaces\Support\Exceptions\MarketplaceException;
use Illuminate\Http\Client\Response;

/**
 * A failed Hepsiburada call - and "failed" is not the same as "non 2xx".
 *
 * Two completely disjoint error shapes travel over this API (HEPSIBURADA.md
 * §8.1, both measured):
 *
 *   A. the catalog business envelope, which arrives with HTTP 200:
 *      {"success": false, "code": 1003, "message": "...", "data": null}
 *      `businessCode: 0` is success; anything else is a failure the status line hides.
 *
 *   B. the Spring transport envelope:
 *      {"timestamp": "...", "status": 404, "error": "Not Found",
 *       "message": "Not Found", "path": "/api/..."}
 *      No `success`, no `code`, no `data` - a parser written for A explodes here.
 *
 * Listing, order and package responses carry neither: they are a bare object or
 * a bare array, and their only failure channel is the HTTP status (envelope C).
 */
final class HepsiburadaApiException extends MarketplaceException
{
    public function __construct(
        string $message,
        public readonly string $endpoint,
        public readonly int $status,
        /** The catalog business code (§8.2). Null on the Spring/bare envelopes. */
        public readonly ?int $businessCode = null,
        public readonly string $body = '',
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(string $endpoint, Response $response): self
    {
        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];

        return new self(
            message: self::describe($endpoint, $response->status(), $payload, $response->body()),
            endpoint: $endpoint,
            status: $response->status(),
            businessCode: self::businessCode($payload),
            body: $response->body(),
        );
    }

    /**
     * HTTP 200 with `success: false` - the failure mode a status-line-only
     * client reports as a success.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromBusinessEnvelope(string $endpoint, int $status, array $payload, string $body): self
    {
        return new self(
            message: self::describe($endpoint, $status, $payload, $body),
            endpoint: $endpoint,
            status: $status,
            businessCode: self::businessCode($payload),
            body: $body,
        );
    }

    /**
     * Business codes are permanent: a wrong category or a malformed file will
     * fail identically forever, and retrying only burns the shared IP budget
     * (§8.2, §8.5).
     */
    public function isPermanent(): bool
    {
        return $this->businessCode !== null && $this->businessCode !== 4000;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function describe(string $endpoint, int $status, array $payload, string $body): string
    {
        $detail = self::text($payload['message'] ?? null);
        $code = self::businessCode($payload);

        if ($code !== null) {
            $detail = "code {$code}".($detail === null ? '' : ": {$detail}");
        } elseif (isset($payload['error'], $payload['path'])) {
            // Spring envelope: `error` is the reason phrase, `path` is what was
            // actually requested - which is how the singular/plural
            // `attribute` vs `attributes` trap of §4.1.3 shows itself.
            $detail = trim(self::text($payload['error']) ?? '');
            $path = self::text($payload['path']);
            $detail = $path === null ? $detail : "{$detail} at {$path}";
        }

        $detail ??= mb_substr(trim($body), 0, 200);

        return "Hepsiburada [{$endpoint}] failed with HTTP {$status}".($detail === '' ? '.' : ": {$detail}");
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function businessCode(array $payload): ?int
    {
        // Only meaningful next to a `success` flag: the Spring envelope has no
        // `code` at all, and the listing poll's `errors[]` are strings.
        if (! array_key_exists('success', $payload) || ! is_numeric($payload['code'] ?? null)) {
            return null;
        }

        return (int) $payload['code'];
    }

    private static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
