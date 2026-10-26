<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Nreljed\Platform\Support\RoleCatalogSynchroniser;

/**
 * Provision every permission in the config catalog into the database.
 *
 * A consumer deploy runs `migrate` and never the seeder, so a permission
 * introduced by a new core release has no row until someone seeds by hand.
 * Because `CheckPermission` reads an explicit slug list (it never passes
 * through `Gate::before`, so admins get no wildcard bypass) that leaves the
 * new route 403 for everyone — including admins.
 *
 * The grant resolution and cache flush live in RoleCatalogSynchroniser so this
 * migration and 2026_10_26_000001_sync_catalog_role_permissions cannot drift.
 * This file is history: it has already run on every install that released
 * before the sync migration, so new catalog rules belong in the later
 * migration, not here.
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
        // Additive backfill. Reversing it would delete permissions that may
        // have been granted to real users, so the catalog simply re-syncs.
    }
};
