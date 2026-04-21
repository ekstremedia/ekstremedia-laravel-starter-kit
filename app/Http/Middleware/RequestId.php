<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every inbound request with a correlation ID.
 *
 * Accepts a client-supplied X-Request-Id when it looks reasonable (UUID or
 * ULID-ish), otherwise mints a fresh UUIDv4. The same id is:
 *   - attached to the log context for every log line emitted on this request,
 *   - shared on the Request instance so controllers/services can reach it,
 *   - echoed back on the response so clients / load balancers / Sentry can
 *     correlate a single HTTP round-trip end to end.
 */
class RequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $this->resolveId($request);

        $request->headers->set(self::HEADER, $id);
        $request->attributes->set('request_id', $id);
        Log::shareContext(['request_id' => $id]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $id, false);

        return $response;
    }

    private function resolveId(Request $request): string
    {
        $candidate = (string) $request->headers->get(self::HEADER, '');

        // Accept anything that's plausibly an identifier — typically UUID,
        // ULID, or a trace id from an upstream proxy. Reject junk to prevent
        // log-header injection with control characters or newlines.
        if ($candidate !== '' && preg_match('/^[A-Za-z0-9\-_]{8,64}$/', $candidate) === 1) {
            return $candidate;
        }

        return (string) Str::uuid();
    }
}
