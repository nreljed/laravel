<?php

namespace App\Services;

use App\Models\Permission;

class PermissionGroups
{
    public static function groups(): array
    {
        return [
            'General' => [
                ['slug' => 'view-dashboard', 'name' => 'View Dashboard', 'description' => 'Access the main dashboard'],
                ['slug' => 'view-profile', 'name' => 'View Profile', 'description' => 'View own profile'],
                ['slug' => 'edit-profile', 'name' => 'Edit Profile', 'description' => 'Edit own name, email, password'],
                ['slug' => 'delete-account', 'name' => 'Delete Account', 'description' => 'Delete own account'],
                ['slug' => 'view-notifications', 'name' => 'View Notifications', 'description' => 'View notifications'],
                ['slug' => 'manage-notifications', 'name' => 'Manage Notifications', 'description' => 'Mark read, delete notifications'],
            ],
            'Blog' => [
                ['slug' => 'posts.view', 'name' => 'View Posts', 'description' => 'View blog posts'],
                ['slug' => 'posts.create', 'name' => 'Create Posts', 'description' => 'Create new blog posts'],
                ['slug' => 'posts.edit', 'name' => 'Edit Posts', 'description' => 'Edit existing blog posts'],
                ['slug' => 'posts.delete', 'name' => 'Delete Posts', 'description' => 'Delete blog posts'],
            ],
            'Testing' => [
                ['slug' => 'test-posts.view', 'name' => 'View Tests', 'description' => 'View tests'],
                ['slug' => 'test-posts.create', 'name' => 'Create Tests', 'description' => 'Create new tests'],
                ['slug' => 'test-posts.edit', 'name' => 'Edit Tests', 'description' => 'Edit existing tests'],
                ['slug' => 'test-posts.delete', 'name' => 'Delete Tests', 'description' => 'Delete tests'],
            ],
            'Administration' => [
                ['slug' => 'manage-users', 'name' => 'Manage Users', 'description' => 'Create, edit, delete users'],
                ['slug' => 'manage-settings', 'name' => 'Manage Settings', 'description' => 'Change application settings'],
                ['slug' => 'manage-roles', 'name' => 'Manage Roles', 'description' => 'Assign and manage roles'],
                ['slug' => 'view-system-health', 'name' => 'View System Health', 'description' => 'View system health status'],
                ['slug' => 'manage-audit-logs', 'name' => 'Manage Audit Logs', 'description' => 'View system audit logs'],
                ['slug' => 'manage-email-settings', 'name' => 'Manage Email Settings', 'description' => 'Configure email settings'],
                ['slug' => 'manage-categories', 'name' => 'Manage Categories', 'description' => 'Manage post categories'],
                ['slug' => 'manage-tags', 'name' => 'Manage Tags', 'description' => 'Manage post tags'],
            ],
        ];
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
