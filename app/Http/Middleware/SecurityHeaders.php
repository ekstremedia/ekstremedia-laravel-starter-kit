<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a baseline set of hardening headers on every response.
 *
 * The defaults here are intentionally conservative: they are safe for the
 * Inertia + PrimeVue stack this kit ships with. The Content-Security-Policy
 * is opt-in because any reasonable CSP depends on the third-party services a
 * consuming app uses (Sentry, Stripe, Pusher, etc.) — set CSP_ENABLED=true
 * and tune CSP_DEFAULT_SRC when you're ready.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // These headers are safe to set unconditionally.
        // SAMEORIGIN (not DENY) because /admin/monitoring embeds the
        // same-origin log-viewer, Horizon, and Pulse dashboards in iframes;
        // SAMEORIGIN still blocks cross-origin embedding which is the actual
        // attack we care about.
        $response->headers->set('X-Content-Type-Options', 'nosniff', false);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN', false);
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), interest-cohort=()',
            false,
        );

        // HSTS only when the request is actually on HTTPS — emitting it on
        // plain HTTP can lock users out if they later hit a misconfigured
        // endpoint.
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
                false,
            );
        }

        if (config('security.csp.enabled')) {
            $response->headers->set(
                'Content-Security-Policy',
                (string) config('security.csp.policy'),
                false,
            );
        }

        return $response;
    }
}
