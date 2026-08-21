<?php

declare(strict_types=1);

namespace App\Support\Sync;

use App\Models\IdempotencyKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dedup layer 1 (BACKEND-PLAN 8.2): the caller supplied `Idempotency-Key`.
 *
 * Laravel ships nothing for this, so the whole mechanism is ours. The claim is
 * a single round trip: `insert ... on conflict do nothing returning *` tells us
 * in one statement whether we won the key or somebody else holds it.
 *
 * Losing the race means one of two things, and they are not the same answer:
 * a finished request replays its recorded outcome, an in flight one gets 409.
 */
final class IdempotencyGuard
{
    private const TTL_HOURS = 24;

    /**
     * Null means the caller owns the key and must do the work.
     *
     * ponytail: expired rows are read as valid rather than pruned here. Add a
     * scheduled cleanup when this guards a public API instead of two panel
     * buttons - replaying a day old outcome is harmless, a stale read is not.
     */
    public function claim(Request $request): ?Response
    {
        $key = $this->key($request);

        if ($key === null) {
            return null;
        }

        $hash = $this->hash($request);

        $claimed = IdempotencyKey::query()->toBase()->insertOrIgnoreReturning([
            'key' => $key,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'endpoint' => $request->method().' '.$request->path(),
            'request_hash' => $hash,
            'response_status' => null,
            'response_body' => null,
            'locked_at' => now(),
            'expires_at' => now()->addHours(self::TTL_HOURS),
        ]);

        if ($claimed->isNotEmpty()) {
            return null;
        }

        $record = IdempotencyKey::query()->find($key);

        if ($record === null) {
            return null;
        }

        if ($record->request_hash !== $hash) {
            return $this->conflict('Bu Idempotency-Key farklı bir istek için kullanılmış.');
        }

        if ($record->response_status === null) {
            return $this->conflict('Aynı istek hâlâ işleniyor.');
        }

        // Panel actions redirect; only a JSON outcome is worth replaying verbatim.
        return $record->response_body === null
            ? back()
            : new JsonResponse($record->response_body, $record->response_status);
    }

    /**
     * Record the outcome so a repeat of the same key replays it.
     */
    public function complete(Request $request, Response $response): Response
    {
        $key = $this->key($request);

        if ($key === null) {
            return $response;
        }

        // A mass update bypasses the casts, so the jsonb column is encoded here.
        IdempotencyKey::query()->whereKey($key)->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => $response instanceof JsonResponse
                ? json_encode($response->getData(true))
                : null,
            'locked_at' => null,
        ]);

        return $response;
    }

    private function key(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key');

        return is_string($key) && $key !== '' ? mb_substr($key, 0, 255) : null;
    }

    private function hash(Request $request): string
    {
        return hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
    }

    private function conflict(string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], Response::HTTP_CONFLICT);
    }
}
