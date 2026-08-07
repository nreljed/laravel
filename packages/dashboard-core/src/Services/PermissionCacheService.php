<?php

namespace Nreljed\DashboardCore\Services;

use Illuminate\Support\Facades\Cache;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\User;

class PermissionCacheService
{
    private const int CACHE_TTL = 3600;

    private const string KEY_ALL_PERMISSIONS = 'permissions:all';

    private const string KEY_ROLE_PERMISSIONS = 'permissions:role:';

    private const string KEY_USER_PERMISSIONS = 'permissions:user:';

    public function getAllPermissions(): array
    {
        return Cache::remember(self::KEY_ALL_PERMISSIONS, self::CACHE_TTL, fn () => Permission::pluck('slug', 'id')->toArray());
    }

    public function getRolePermissionSlugs(int $roleId): array
    {
        $key = self::KEY_ROLE_PERMISSIONS.$roleId;

        return Cache::remember($key, self::CACHE_TTL, function () use ($key, $roleId) {
            $roleKeys = Cache::get('permissions:role_keys', []);
            $roleKeys[$key] = true;
            Cache::put('permissions:role_keys', array_keys($roleKeys), self::CACHE_TTL);

            return Role::find($roleId)?->permissions()->pluck('slug')->toArray() ?? [];
        });
    }

    public function getUserPermissionSlugs(int $userId): array
    {
        $key = self::KEY_USER_PERMISSIONS.$userId;

        return Cache::remember($key, self::CACHE_TTL, function () use ($key, $userId) {
            $userKeys = Cache::get('permissions:user_keys', []);
            $userKeys[$key] = true;
            Cache::put('permissions:user_keys', array_keys($userKeys), self::CACHE_TTL);

            $user = User::with(['roles.permissions', 'permissions'])->find($userId);

            if (! $user) {
                return [];
            }

            $slugs = [];

            foreach ($user->roles as $role) {
                foreach ($role->permissions as $permission) {
                    $slugs[$permission->slug] = true;
                }
            }

            foreach ($user->permissions as $permission) {
                $slugs[$permission->slug] = true;
            }

            return array_keys($slugs);
        });
    }

    public function forgetAll(): void
    {
        Cache::forget(self::KEY_ALL_PERMISSIONS);
    }

    public function forgetRole(int $roleId): void
    {
        Cache::forget(self::KEY_ROLE_PERMISSIONS.$roleId);
    }

    public function forgetUser(int $userId): void
    {
        Cache::forget(self::KEY_USER_PERMISSIONS.$userId);
    }

    public function flushAll(): void
    {
        Cache::forget(self::KEY_ALL_PERMISSIONS);

        // Clear all role permission caches
        $roleKeys = Cache::get('permissions:role_keys', []);
        foreach ($roleKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('permissions:role_keys');

        // Clear all user permission caches
        $userKeys = Cache::get('permissions:user_keys', []);
        foreach ($userKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('permissions:user_keys');
    }
}
