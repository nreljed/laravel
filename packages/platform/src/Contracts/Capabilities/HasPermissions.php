<?php

namespace Nreljed\Platform\Contracts\Capabilities;

/**
 * The feature declares permission slugs.
 *
 * Slugs are registered into the permission catalog and auto-gated by the
 * PermissionGateServiceProvider. Use kebab-case for global permissions
 * (`manage-users`) and dot notation for feature-scoped ones (`posts.view`).
 */
interface HasPermissions
{
    /**
     * Permission definitions. Each entry is either an array with `slug`,
     * optional `name`, `description` and `group` (the feature name is used when
     * `group` is omitted) or a plain slug string.
     *
     * @return array<int, array<string, mixed>|string>
     */
    public function permissions(): array;
}
