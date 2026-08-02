<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Http\Requests\Admin\StoreRoleRequest;
use Nreljed\DashboardCore\Http\Requests\Admin\UpdateRoleRequest;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Services\AuditService;
use Nreljed\DashboardCore\Services\PermissionCacheService;
use Nreljed\DashboardCore\Services\PermissionGroups;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users', 'permissions')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
            ]);

        return Inertia::render('dashboard/admin/roles/index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $permissionGroups = PermissionGroups::enrichWithDb(PermissionGroups::groups());

        return Inertia::render('dashboard/admin/roles/form', [
            'role' => null,
            'permissionGroups' => $permissionGroups,
        ]);
    }

    public function store(StoreRoleRequest $request, PermissionCacheService $cache): RedirectResponse
    {
        $role = Role::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
        ]);

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        $cache->flushAll();

        AuditService::log(AuditAction::RoleAssigned, $request->user()->id, [
            'role' => $role->slug,
            'permission_ids' => $request->permission_ids ?? [],
        ]);

        return redirect()->route('dashboard.admin.roles.index')
            ->with('flash', ['success' => 'Role created successfully.']);
    }

    public function edit(Role $role): Response
    {
        $role->load('permissions');

        $permissionGroups = PermissionGroups::enrichWithDb(
            PermissionGroups::groups(),
            $role->permissions->pluck('slug')->toArray()
        );

        return Inertia::render('dashboard/admin/roles/form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
            ],
            'permissionGroups' => $permissionGroups,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, PermissionCacheService $cache): RedirectResponse
    {
        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        if ($request->has('permission_ids')) {
            if ($role->slug === 'admin') {
                return redirect()->route('dashboard.admin.roles.index')
                    ->with('flash', ['error' => 'The Admin role permissions cannot be modified.']);
            }

            $oldIds = $role->permissions()->pluck('id')->toArray();
            $newIds = $request->permission_ids ?? [];

            $role->permissions()->sync($newIds);

            $revokedIds = array_values(array_diff($oldIds, $newIds));
            if (! empty($revokedIds)) {
                AuditService::log(AuditAction::PermissionRevoked, $request->user()->id, [
                    'role' => $role->slug,
                    'permission_ids' => $revokedIds,
                ]);
            }
        }

        $cache->flushAll();

        AuditService::log(AuditAction::PermissionAssigned, $request->user()->id, [
            'role' => $role->slug,
            'permission_ids' => $request->permission_ids ?? [],
        ]);

        return redirect()->route('dashboard.admin.roles.index')
            ->with('flash', ['success' => 'Role updated successfully.']);
    }

    public function destroy(Request $request, Role $role, PermissionCacheService $cache): RedirectResponse
    {
        if ($role->slug === 'admin') {
            return redirect()->route('dashboard.admin.roles.index')
                ->with('flash', ['error' => 'The Admin role cannot be deleted.']);
        }

        $userIds = $role->users()->pluck('id')->toArray();
        $permissionIds = $role->permissions()->pluck('id')->toArray();

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        $cache->flushAll();

        AuditService::log(AuditAction::RoleRevoked, $request->user()->id, [
            'role' => $role->slug,
            'target_user_ids' => $userIds,
            'permission_ids' => $permissionIds,
        ]);

        return redirect()->route('dashboard.admin.roles.index')
            ->with('flash', ['success' => 'Role deleted successfully.']);
    }
}
