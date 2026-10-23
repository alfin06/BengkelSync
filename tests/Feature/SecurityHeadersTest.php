<?php

it('sends the basic security headers on every page', function () {
    $this->get(route('login'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Content-Security-Policy');
});

it('sends a nonce-based CSP and HSTS when enabled', function () {
    config(['security.csp' => true, 'security.hsts' => true]);

    $response = $this->get(route('login'));

    $csp = (string) $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $csp, $matches);

    expect($csp)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->and($matches[1] ?? null)->not->toBeNull();

    // The inline Ziggy routes script carries the same nonce.
    $response->assertSee('nonce="'.$matches[1].'"', escape: false)
        ->assertHeader('Strict-Transport-Security');
});
