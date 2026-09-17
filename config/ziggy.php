<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ziggy Route Scoping
    |--------------------------------------------------------------------------
    |
    | Narrow the routes injected into every Inertia page (shared `ziggy`
    | prop). Routes listed here are backend-only or browser-direct and are
    | never referenced by this app's frontend (verified against all TSX/TS
    | sources), so shipping them in the browser payload is pure waste.
    |
    | Add a name, set of names, or `*.` wildcard pattern to exclude more of
    | this app's routes that the frontend does not need.
    |
    */

    'except' => [
        // Backend deploy endpoints (token-protected; driven by CI, not JS).
        'deploy.*',

        // Maintenance-mode internal endpoints (cron + admin bypass only).
        'maintenance.*',

        // Browser-direct / crawler / health endpoints — not called via route().
        'csp.report',
        'robots',
        'sitemap',

        // Laravel Boost devtool (browser log ingestion).
        'boost.browser-logs',

        // Local storage media routes (frontend uses /storage/ URLs directly).
        'storage.local',
        'storage.local.upload',

        // CSRF cookie route (frontend reads the meta tag instead).
        'sanctum.csrf-cookie',

        // Email verification + password reset pages (reached via emailed links).
        'verification.notice',
        'verification.verified',
        'verification.verify',
        'password.reset',
        'password.update',
    ],

];
