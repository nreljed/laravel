<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
use Nreljed\DashboardCore\Services\SettingsService;

class RoleController extends Controller
{
    public function index(): Response
    {
        $defaultRoleSlug = SettingsService::get('default_role', config('roles.default_role'));

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
                'is_system' => $role->isSystem(),
                'is_default' => $role->slug === $defaultRoleSlug,
                'updated_at' => $role->updated_at?->toISOString(),
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
            'roles' => $this->roleOptions(),
            'defaultRoleId' => $this->defaultRoleId(),
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

        AuditService::log(AuditAction::RoleCreated, $request->user()->id, [
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
                'is_system' => $role->isSystem(),
                'is_default' => $role->slug === $this->defaultRoleSlug(),
                'updated_at' => $role->updated_at?->toISOString(),
            ],
            'permissionGroups' => $permissionGroups,
            'roles' => $this->roleOptions(),
            'defaultRoleId' => $this->defaultRoleId(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, PermissionCacheService $cache): RedirectResponse
    {
        if ($role->isSystem()) {
            return redirect()->route('dashboard.admin.roles.index')
                ->with('flash', ['error' => 'System roles are managed by the config catalog and cannot be edited here.']);
        }

        $oldIds = $role->permissions()->pluck('id')->toArray();

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        AuditService::log(AuditAction::RoleUpdated, $request->user()->id, [
            'role' => $role->slug,
        ]);

        if ($request->has('permission_ids')) {
            $newIds = $request->permission_ids ?? [];

            if (array_diff($newIds, $oldIds) !== [] || array_diff($oldIds, $newIds) !== []) {
                $role->permissions()->sync($newIds);

                AuditService::log(AuditAction::RolePermissionChanged, $request->user()->id, [
                    'role' => $role->slug,
                    'granted_ids' => array_values(array_diff($newIds, $oldIds)),
                    'revoked_ids' => array_values(array_diff($oldIds, $newIds)),
                ]);
            }
        }

        $cache->flushAll();

        return redirect()->route('dashboard.admin.roles.index')
            ->with('flash', ['success' => 'Role updated successfully.']);
    }

    public function destroy(Request $request, Role $role, PermissionCacheService $cache): RedirectResponse
    {
        if ($role->isSystem()) {
            return redirect()->route('dashboard.admin.roles.index')
                ->with('flash', ['error' => 'System roles are managed by the config catalog and cannot be deleted.']);
        }

        if ($role->users()->exists()) {
            return redirect()->route('dashboard.admin.roles.index')
                ->with('flash', ['error' => 'The role has '.$role->users()->count().' user(s) assigned. Reassign or remove them before deleting the role.']);
        }

        if ($role->slug === $this->defaultRoleSlug()) {
            return redirect()->route('dashboard.admin.roles.index')
                ->with('flash', ['error' => 'The role currently set as the default registration role cannot be deleted. Choose a different default role first.']);
        }

        $role->permissions()->detach();
        $role->delete();

        $cache->flushAll();

        AuditService::log(AuditAction::RoleDeleted, $request->user()->id, [
            'role' => $role->slug,
        ]);

        return redirect()->route('dashboard.admin.roles.index')
            ->with('flash', ['success' => 'Role deleted successfully.']);
    }

    public function duplicate(Request $request, Role $role, PermissionCacheService $cache): RedirectResponse
    {
        $newRole = Role::create([
            'name' => 'Copy of '.$role->name,
            'slug' => $this->uniqueSlug('copy-of-'.$role->slug),
            'description' => $role->description,
            'sort_order' => (Role::max('sort_order') ?? 0) + 1,
        ]);

        $newRole->permissions()->sync($role->permissions()->pluck('id')->toArray());

        $cache->flushAll();

        AuditService::log(AuditAction::RoleDuplicated, $request->user()->id, [
            'role' => $newRole->slug,
            'source_role' => $role->slug,
        ]);

        return redirect()->route('dashboard.admin.roles.edit', $newRole)
            ->with('flash', ['success' => 'Role duplicated successfully. Review the copy and adjust as needed.']);
    }

    protected function uniqueSlug(string $base): string
    {
        $slug = Str::slug($base, '-');
        $candidate = $slug;
        $suffix = 2;

        while (Role::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function defaultRoleSlug(): string
    {
        return SettingsService::get('default_role', config('roles.default_role'));
    }

    protected function defaultRoleId(): ?int
    {
        return Role::where('slug', $this->defaultRoleSlug())->value('id');
    }

    /**
     * Roles offered as "Start from" templates on the create form. System
     * roles are excluded so a user cannot mint an all-powerful role (e.g.
     * starting from admin) while still reading the config catalog.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     slug: string,
     *     is_system: bool,
     *     permission_ids: list<int>,
     * }>
     */
    protected function roleOptions(): array
    {
        return Role::with('permissions')
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug'])
            ->reject(fn (Role $role) => $role->isSystem())
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'is_system' => false,
                'permission_ids' => $role->permissions->pluck('id')->toArray(),
            ])
            ->values()
            ->all();
    }
}
