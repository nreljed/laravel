<?php

namespace Nreljed\DashboardCore\Services;

use Nreljed\DashboardCore\Models\Permission;

class PermissionGroups
{
    /**
     * Curated order in which groups should be displayed.
     *
     * @var list<string>
     */
    private const array GROUP_ORDER = ['General', 'Blog', 'Testing', 'Administration'];

    /**
     * Build permission groups from the core config catalog and registered
     * feature providers so the UI can never drift from the source of truth.
     *
     * @return array<string, list<array{slug: string, name: string, description: string}>>
     */
    public static function groups(): array
    {
        $groups = [];

        foreach (config('roles.permissions', []) as $permission) {
            $groups[$permission['group'] ?? 'Administration'][] = [
                'slug' => $permission['slug'],
                'name' => $permission['name'],
                'description' => $permission['description'],
            ];
        }

        foreach (app(FeatureRegistry::class)->permissions() as $permission) {
            if (! is_array($permission)) {
                continue;
            }

            $groups[$permission['group'] ?? 'Administration'][] = [
                'slug' => $permission['slug'],
                'name' => $permission['name'],
                'description' => $permission['description'] ?? '',
            ];
        }

        $ordered = [];
        foreach (self::GROUP_ORDER as $groupName) {
            if (isset($groups[$groupName])) {
                $ordered[$groupName] = $groups[$groupName];
                unset($groups[$groupName]);
            }
        }

        foreach ($groups as $groupName => $permissions) {
            $ordered[$groupName] = $permissions;
        }

        return $ordered;
    }

    public static function allSlugs(): array
    {
        $slugs = [];
        foreach (static::groups() as $group) {
            foreach ($group as $perm) {
                $slugs[] = $perm['slug'];
            }
        }

        return $slugs;
    }

    public static function enrichWithDb(array $groups, ?array $assignedSlugs = null): array
    {
        $permissionsBySlug = Permission::all()->keyBy('slug');

        $result = [];
        foreach ($groups as $groupName => $group) {
            $enriched = [];
            foreach ($group as $perm) {
                $dbPerm = $permissionsBySlug->get($perm['slug']);
                if ($dbPerm === null) {
                    continue;
                }
                $enriched[] = [
                    'id' => $dbPerm->id,
                    'name' => $dbPerm->name,
                    'slug' => $dbPerm->slug,
                    'description' => $dbPerm->description,
                    'assigned' => $assignedSlugs !== null
                        ? in_array($dbPerm->slug, $assignedSlugs, true)
                        : false,
                ];
            }

            $result[] = [
                'group' => $groupName,
                'permissions' => $enriched,
            ];
        }

        return $result;
    }
}
