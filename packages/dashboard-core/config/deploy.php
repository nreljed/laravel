<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deploy Endpoint Token
    |--------------------------------------------------------------------------
    |
    | Shared secret required by the /deploy/* endpoints. Must match the
    | DEPLOY_TOKEN GitHub secret used by the CI/CD pipeline.
    |
    */

    'token' => env('DEPLOY_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | GitHub Repository
    |--------------------------------------------------------------------------
    |
    | Repository queried by the deploy check endpoint for the latest release.
    | Projects override this to their own repo (e.g. nreljed/laravel.shop).
    |
    */

    'github_repo' => env('GITHUB_REPO', 'nreljed/laravel'),

    /*
    |--------------------------------------------------------------------------
    | Auto Deploy Schedule
    |--------------------------------------------------------------------------
    |
    | When enabled, the core service provider schedules `deploy:auto` every
    | five minutes so every project shares the same server-side self-deploy
    | behaviour without any host-level routes/console.php wiring.
    |
    */

    'auto_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Deploy Lock TTL
    |--------------------------------------------------------------------------
    |
    | Seconds a deploy lock stays valid before it can be re-acquired.
    |
    */

    'lock_ttl' => 600,

    /*
    |--------------------------------------------------------------------------
    | Deploy Marker Paths
    |--------------------------------------------------------------------------
    |
    | Filesystem paths for the deploy lock and the last-deployed version
    | marker. Overridable so test workers can isolate their own copies and
    | avoid racing each other over the shared markers.
    |
    */

    'lock_path' => base_path('.deploy-lock'),

    'version_path' => base_path('.deployed-version'),

];
