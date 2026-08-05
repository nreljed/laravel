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
    | Deploy Lock TTL
    |--------------------------------------------------------------------------
    |
    | Seconds a deploy lock stays valid before it can be re-acquired.
    |
    */

    'lock_ttl' => 600,

];
