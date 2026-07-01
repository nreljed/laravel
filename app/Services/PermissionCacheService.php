<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionCacheService
{
    private const CACHE_TTL = 3600;

    private const KEY_ALL_PERMISSIONS = 'permissions:all';

    private const KEY_ROLE_PERMISSIONS = 'permissions:role:';

    private const KEY_USER_PERMISSIONS = 'permissions:user:';

    public function getAllPermissions(): array
    {
        return Cache::remember(self::KEY_ALL_PERMISSIONS, self::CACHE_TTL, function () {
            return Permission::pluck('slug', 'id')->toArray();
        });
    }

    public function getRolePermissionSlugs(int $roleId): array
    {
        return Cache::remember(self::KEY_ROLE_PERMISSIONS.$roleId, self::CACHE_TTL, function () use ($roleId) {
            return Role::find($roleId)?->permissions()->pluck('slug')->toArray() ?? [];
        });
    }

    public function getUserPermissionSlugs(int $userId): array
    {
        return Cache::remember(self::KEY_USER_PERMISSIONS.$userId, self::CACHE_TTL, function () use ($userId) {
            $user = User::with('roles.permissions')->find($userId);

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
        $this->forgetAll();

        foreach (Role::pluck('id') as $id) {
            $this->forgetRole($id);
        }

        foreach (User::pluck('id') as $id) {
            $this->forgetUser($id);
        }
    }
}
