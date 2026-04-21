<?php

it('applies baseline hardening headers to every web response', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Permissions-Policy'))
        ->toContain('camera=()')
        ->toContain('microphone=()');
});

it('does not emit HSTS on plain HTTP', function () {
    $response = $this->get('/');

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('emits a CSP only when CSP_ENABLED is true', function () {
    $baseline = $this->get('/');
    expect($baseline->headers->has('Content-Security-Policy'))->toBeFalse();

    config(['security.csp.enabled' => true, 'security.csp.policy' => "default-src 'self'"]);

    $withCsp = $this->get('/');
    $withCsp->assertHeader('Content-Security-Policy', "default-src 'self'");
});
