<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Http\Requests\Permission\StorePermissionRequest;
use App\Models\Permission;
use App\Services\PermissionCacheService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(Request $request): Response
    {
        $permissions = Permission::orderBy('slug')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'description' => $p->description,
                'guard_name' => $p->guard_name,
                'roles_count' => $p->roles()->count(),
                'created_at' => $p->created_at->toISOString(),
            ]);

        return Inertia::render('dashboard/admin/permissions/index', [
            'permissions' => $permissions,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('dashboard/admin/permissions/form', [
            'permission' => null,
        ]);
    }

    public function store(StorePermissionRequest $request, PermissionCacheService $cache): RedirectResponse
    {
        Permission::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'guard_name' => $request->guard_name ?? 'web',
        ]);

        $cache->flushAll();

        return redirect()->route('dashboard.admin.permissions.index')
            ->with('flash', ['success' => 'Permission created successfully.']);
    }

    public function edit(Permission $permission): Response
    {
        return Inertia::render('dashboard/admin/permissions/form', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'description' => $permission->description,
                'guard_name' => $permission->guard_name,
            ],
        ]);
    }

    public function update(UpdatePermissionRequest $request, Permission $permission, PermissionCacheService $cache): RedirectResponse
    {

        $permission->update([
            'name' => $request->name,
            'slug' => $request->slug,
            'description' => $request->description,
            'guard_name' => $request->guard_name ?? 'web',
        ]);

        $cache->flushAll();

        return redirect()->route('dashboard.admin.permissions.index')
            ->with('flash', ['success' => 'Permission updated successfully.']);
    }

    public function destroy(Permission $permission, PermissionCacheService $cache): RedirectResponse
    {
        $permission->roles()->detach();
        $permission->users()->detach();
        $permission->delete();

        $cache->flushAll();

        return redirect()->route('dashboard.admin.permissions.index')
            ->with('flash', ['success' => 'Permission deleted successfully.']);
    }
}
