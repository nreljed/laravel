<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Nreljed\Platform\Support\RoleCatalogSynchroniser;

/**
 * Re-sync the config catalog after a release that changed its denies.
 *
 * `2026_10_20_000001_provision_config_permissions_into_database` has already
 * run on every installation that shipped it, so editing its body can never
 * re-execute it: migrations run once. A permission that `'*'` had already
 * flattened onto a catch-all role therefore stays granted forever, even after
 * the catalog adds a `!slug` exclusion for it.
 *
 * This migration is the delivery mechanism for that class of change. It is the
 * same idempotent reconciliation (create missing rows, grant what the catalog
 * grants, revoke what it explicitly denies) and is safe to re-run.
 *
 * Ship a new dated migration calling RoleCatalogSynchroniser whenever the
 * catalog's exclusions change; never edit an earlier migration to do it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('permission_role')) {
            return;
        }

        app(RoleCatalogSynchroniser::class)->sync();
    }

    public function down(): void
    {
        // The catalog re-syncs forward. Reversing would re-grant permissions
        // the catalog deliberately denies.
    }
};
