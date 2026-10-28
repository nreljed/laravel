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

    'auto_enabled' => env('DEPLOY_AUTO_ENABLED', true),

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
    | Environment Backup Retention
    |--------------------------------------------------------------------------
    |
    | How many `.env.backup.<timestamp>` snapshots to keep beside the live
    | `.env`. Every env-modifying endpoint writes a snapshot before touching
    | the file; older ones are pruned so the directory never grows without
    | bound. On release-layout hosts the snapshots land inside the active
    | release and are additionally pruned by release-cleanup.
    |
    */

    'env_backup_retention' => (int) env('DEPLOY_ENV_BACKUP_RETENTION', 5),

    /*
    |--------------------------------------------------------------------------
    | Full .env Overwrite
    |--------------------------------------------------------------------------
    |
    | The DANGEROUS `/deploy/env`-style full-content overwrite (`updateEnv`)
    | is not routed and is additionally locked behind this opt-in flag. Flip
    | it only if a future pipeline genuinely needs to replace the whole file;
    | the values-based endpoint (`updateEnvValues`) is the supported path.
    |
    */

    'allow_full_env_overwrite' => env('DEPLOY_ALLOW_FULL_ENV_OVERWRITE', false),

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

    /*
    |--------------------------------------------------------------------------
    | Safe Install Boot Check
    |--------------------------------------------------------------------------
    |
    | Before a release is swapped into the live install, the staged tree is
    | boot-checked via `php artisan --version` (with the live .env copied in).
    | Set to `false` only for first-time bootstrap when the app cannot boot yet
    | (e.g. no .env / no database). On the server this can be toggled through
    | the DEPLOY_BOOT_CHECK env var — see .env.example.
    |
    */

    'boot_check' => env('DEPLOY_BOOT_CHECK', true),

    /*
    |--------------------------------------------------------------------------
    | Safe Install Boot Check Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds allowed for the staged boot check before it is treated as failed.
    |
    */

    'boot_check_timeout' => 90,

    /*
    |--------------------------------------------------------------------------
    | Deploy Endpoint IP Allowlist
    |--------------------------------------------------------------------------
    |
    | Optional CIDR allowlist restricting the /deploy/* endpoints to known
    | CI/runner IPs. Empty by default (any IP may call the endpoints once it
    | knows the token). Set DEPLOY_IP_ALLOWLIST="1.2.3.4,10.0.0.0/8" on the
    | server to lock deploy writes to your CI egress IPs. Each value is a
    | single IP or CIDR; requests from non-listed IPs are rejected with 403.
    |
    */

    'ip_allowlist' => array_values(array_filter(array_map('trim', explode(',', (string) env('DEPLOY_IP_ALLOWLIST', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Query-String Token Fallback
    |--------------------------------------------------------------------------
    |
    | Historically the /deploy/* endpoints accepted `?token=` on the URL,
    | which can leak into proxy/access logs and the Referer header. The
    | middleware prefers the `Authorization: Bearer` header and only falls
    | back to the query string while this flag is on. Default true so every
    | consumer keeps deploying unchanged during migration; flip
    | DEPLOY_ALLOW_QUERY_TOKEN=false in each server `.env` after every
    | consumer workflow has switched to the header (see docs/deploy.md).
    |
    */

    'allow_query_token' => (bool) env('DEPLOY_ALLOW_QUERY_TOKEN', true),

    /*
    |--------------------------------------------------------------------------
    | Install Root
    |--------------------------------------------------------------------------
    |
    | Directory the staged release is swapped into. Defaults to the project root
    | (base_path). Overridable so tests can run the swap inside a sandbox
    | instead of replacing the live vendor/ of the running process (which
    | Windows, and good sense, will not allow).
    |
    */

    'install_root' => base_path(),

];
