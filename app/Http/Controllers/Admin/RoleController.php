<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
        ]);
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

        return Inertia::render('Admin/Roles/Form', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
            ],
            'permissionGroups' => $allPermissions,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        if ($request->has('permission_ids')) {
            $role->permissions()->sync($request->permission_ids);
        }

        return redirect()->route('dashboard.admin.roles.index')
            ->with('flash', ['success' => 'Role updated successfully.']);
    }
}
