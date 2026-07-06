<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use App\Services\PermissionCacheService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = Role::withCount('users', 'permissions')
            ->orderBy('name')
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
        $permissionGroups = Permission::orderBy('name')
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->slug)[0] ?? 'general')
            ->map(fn ($group, $key) => [
                'group' => $key,
                'permissions' => $group->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'description' => $p->description,
                    'assigned' => false,
                ]),
            ])
            ->values();

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

        $allPermissions = Permission::orderBy('name')
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->slug)[0] ?? 'general')
            ->map(fn ($group, $key) => [
                'group' => $key,
                'permissions' => $group->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'slug' => $p->slug,
                    'description' => $p->description,
                    'assigned' => $role->permissions->contains('id', $p->id),
                ]),
            ])
            ->values();

        return Inertia::render('dashboard/admin/roles/form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
            ],
            'permissionGroups' => $allPermissions,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, PermissionCacheService $cache): RedirectResponse
    {

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        $cache->flushAll();

        AuditService::log(AuditAction::PermissionAssigned, $request->user()->id, [
            'role' => $role->slug,
            'permission_ids' => $request->permission_ids ?? [],
        ]);

        return redirect()->route('dashboard.admin.roles.index')
            ->with('flash', ['success' => 'Role updated successfully.']);
    }

    public function destroy(Role $role, PermissionCacheService $cache): RedirectResponse
    {
        if ($role->slug === 'admin') {
            return redirect()->route('dashboard.admin.roles.index')
                ->with('flash', ['error' => 'The admin role cannot be deleted.']);
        }

        $role->users()->detach();
        $role->permissions()->detach();
        $role->delete();

        $cache->flushAll();

        return redirect()->route('dashboard.admin.roles.index')
            ->with('flash', ['success' => 'Role deleted successfully.']);
    }
}
