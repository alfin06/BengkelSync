<?php

return [
    /*
    | Content-Security-Policy header with a per-request nonce for scripts.
    | Off in local development because the Vite dev server injects inline scripts.
    */
    'csp' => (bool) env('SECURITY_CSP', env('APP_ENV') === 'production'),

    /*
    | Strict-Transport-Security. Only enable when the site is served over HTTPS.
    */
    'hsts' => (bool) env('SECURITY_HSTS', env('APP_ENV') === 'production'),
];
