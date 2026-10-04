---
description: Add or change a permission slug and grant it to roles, with the catalog, gate and middleware wiring verified
agent: build
---

# Add or change a permission

RBAC here is **slug-based and platform-owned** (`Nreljed\Platform\Models\Role` /
`Permission`) - **not** Spatie. Permissions come from a config catalog plus what
feature providers declare.

## 1. Where permissions come from

Three sources merge into the catalog:

1. `vendor/nreljed/platform/config/roles.php` - the base catalog and default roles.
2. The app's `config/roles.php` delta, **if published**. This file does not exist in
   this repo; publish it only if the app genuinely needs extra roles.
3. Each `FeatureProvider::permissions()` output, collected by `FeatureRegistry`.

## 2. Pick the slug correctly

- **Feature-scoped**: `posts.view`, `posts.create`, `posts.edit`, `posts.delete` -
  dot notation, tied to the feature slug.
- **Global**: `manage-users`, `manage-settings` - kebab-case.
- **Never snake_case.** `manage_users` is wrong even if it appears somewhere.

A slug that does not exist in the catalog is the most dangerous failure here: a
route protected by `permission:does-not-exist` can silently authorize everyone,
depending on how the gate resolves an unknown ability.

## 3. Add it

For a **feature** permission, declare it in the provider's `permissions()` method -
do not hand-edit the platform catalog:

```php
public function permissions(): array
{
    return ['inventory.view', 'inventory.edit'];
}
```

For a **global** permission, add it to the base catalog in the upstream platform
repo (`C:\Users\nrelj\Local Sites\platform\config\roles.php`), never in a mirror.

Then grant it to the right roles in the same catalog's `roles` definition. Roles are
`'*'` (all) or slug-mapped.

## 4. Seed and verify the catalog

```bash
php artisan db:seed --class="Nreljed\\Platform\\Database\\Seeders\\RolesAndPermissionsSeeder"
php artisan permissions:list
```

The seeder is idempotent, so re-running is safe. Verify the slug actually landed -
do not assume.

## 5. Enforce it in all three places

Check which of these the surrounding code uses and be consistent:

- Route middleware `permission:<slug>`
- Controller `$this->authorize()`
- `FormRequest::authorize()`

Middleware alone is weaker than it looks: the controller stays reachable from other
routes. Confirm the action is not exposed elsewhere without the check.

## 6. Check the gate actually registered

`vendor/nreljed/platform/src/Providers/PermissionGateServiceProvider.php`
auto-registers gates from `FeatureRegistry::permissions()` plus the `permissions`
table. If a gate is not firing:

```bash
php artisan permissions:list
php artisan tinker   # Gate::has('inventory.edit')
```

Also clear the permission cache - `PermissionCacheService` caches lookups.

## Report

The slug, where it was declared, which roles received it, the output of
`permissions:list` showing it present, and which of the three enforcement points you
verified. Distinguish "ran and passed" from "did not run".