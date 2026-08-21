<?php

namespace App\Marketplaces\Trendyol\Exceptions;

use App\Marketplaces\Support\Exceptions\MarketplaceException;
use Illuminate\Http\Client\Response;

/**
 * A non 2xx Trendyol response.
 *
 * Trendyol ships three different error envelopes (TRENDYOL.md 8.1) and a parser
 * has to tolerate all of them:
 *
 *   1. the documented one: {"errors":[{"key","message","errorCode"}]}
 *   2. on 401:             {"exception":"ClientApiAuthenticationException"}
 *   3. order services/429: {"error":"...","message":"..."}
 *
 * `errorCode` is only the HTTP status as a string, so `key` is the machine
 * readable discriminator and the only field worth branching on.
 */
final class TrendyolApiException extends MarketplaceException
{
    /**
     * @param  list<array{key: string, message: string}>  $errors
     */
    public function __construct(
        string $message,
        public readonly string $endpoint,
        public readonly int $status,
        public readonly array $errors = [],
        public readonly string $body = '',
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(string $endpoint, Response $response): self
    {
        $errors = self::parseErrors($response->json());

        $summary = $errors === []
            ? mb_substr(trim($response->body()), 0, 200)
            : implode(', ', array_map(
                static fn (array $error): string => "{$error['key']}: {$error['message']}",
                $errors,
            ));

        return new self(
            message: "Trendyol [{$endpoint}] failed with HTTP {$response->status()}".($summary === '' ? '.' : ": {$summary}"),
            endpoint: $endpoint,
            status: $response->status(),
            errors: $errors,
            body: $response->body(),
        );
    }

    /**
     * @return list<array{key: string, message: string}>
     */
    private static function parseErrors(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        if (isset($payload['errors']) && is_array($payload['errors'])) {
            $errors = [];

            foreach ($payload['errors'] as $error) {
                if (is_array($error)) {
                    $errors[] = [
                        'key' => (string) ($error['key'] ?? ''),
                        'message' => (string) ($error['message'] ?? ''),
                    ];
                }
            }

            return $errors;
        }

        foreach (['exception', 'error'] as $key) {
            if (isset($payload[$key]) && is_scalar($payload[$key])) {
                return [[
                    'key' => (string) $payload[$key],
                    'message' => (string) ($payload['message'] ?? $payload[$key]),
                ]];
            }
        }

        return [];
    }
}
