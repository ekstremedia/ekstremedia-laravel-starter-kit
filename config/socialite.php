<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Socialite gate
    |--------------------------------------------------------------------------
    |
    | The kit ships Socialite wired but disabled. Flip SOCIALITE_ENABLED=true
    | and set the provider credentials below before the `Sign in with …`
    | buttons appear on the login / register pages.
    |
    */

    'enabled' => (bool) env('SOCIALITE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Enabled providers
    |--------------------------------------------------------------------------
    |
    | Each provider keys into a `services.{provider}` block so Laravel's native
    | config channel keeps driving Socialite — no duplication.
    |
    */

    'providers' => [
        'google' => (bool) env('SOCIALITE_GOOGLE_ENABLED', false),
        'github' => (bool) env('SOCIALITE_GITHUB_ENABLED', false),
    ],
];
