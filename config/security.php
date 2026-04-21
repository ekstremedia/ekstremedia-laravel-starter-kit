<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Left disabled by default because any useful CSP depends on the third
    | party services you pull in (Sentry, Stripe, Pusher, fonts.google.com).
    | Tune the `policy` string to your integrations, then enable.
    |
    */

    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', false),
        'policy' => env(
            'CSP_POLICY',
            "default-src 'self'; "
            ."img-src 'self' data: blob: https:; "
            ."font-src 'self' data:; "
            ."style-src 'self' 'unsafe-inline'; "
            ."script-src 'self'; "
            ."connect-src 'self' ws: wss:; "
            ."frame-ancestors 'none'; "
            ."base-uri 'self'; "
            ."form-action 'self'",
        ),
    ],
];
