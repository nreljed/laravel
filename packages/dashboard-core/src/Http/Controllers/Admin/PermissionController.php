<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Models\Permission;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Services\PermissionCacheService;
use Nreljed\DashboardCore\Services\PermissionGroups;

class PermissionController extends Controller
{
    public function manage(): Response
    {
        $roles = Role::orderBy('sort_order')
            ->get()
            ->map(fn (Role $role) => [
                'slug' => $role->slug,
                'name' => $role->name,
                'system' => $role->isSystem(),
                'capabilities' => $role->permissions()->pluck('slug')->toArray(),
            ]);

        $permissionGroups = PermissionGroups::groups();

        return Inertia::render('dashboard/admin/permissions/manage', [
            'roles' => $roles,
            'groups' => $permissionGroups,
        ]);
    }

    public function toggle(Request $request, PermissionCacheService $cache): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|string|exists:roles,slug',
            'capability' => 'required|string|exists:permissions,slug',
            'grant' => 'required|boolean',
        ]);

        $role = Role::where('slug', $validated['role'])->firstOrFail();

        if ($role->isSystem()) {
            return response()->json([
                'error' => 'The '.$role->name.' role is locked and cannot be modified.',
            ], 400);
        }

        $permission = Permission::where('slug', $validated['capability'])->firstOrFail();

        if ($validated['grant']) {
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        } else {
            $role->permissions()->detach($permission->id);
        }

        $cache->flushAll();

        return response()->json([
            'success' => true,
            'message' => ($validated['grant'] ? 'Granted' : 'Revoked').' '.$validated['capability'].' for '.$role->slug,
        ]);
    }
}
