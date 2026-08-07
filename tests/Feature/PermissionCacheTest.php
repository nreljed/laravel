<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Nreljed\DashboardCore\Models\Role;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class PermissionCacheTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        Cache::flush();
    }

    public function test_assign_role_invalidates_cached_permissions(): void
    {
        $user = $this->regularUser();

        $this->assertFalse($user->hasPermission('posts.manage'));

        $user->assignRole('admin');

        $this->assertTrue($user->hasPermission('posts.manage'));
        $this->assertTrue($user->isAdmin());
    }

    public function test_admin_user_update_promoting_role_invalidates_permission_cache(): void
    {
        $admin = $this->admin();
        $user = $this->regularUser();
        $adminRole = Role::where('slug', 'admin')->first();

        $this->assertFalse($user->hasPermission('posts.manage'));

        $this->actingAs($admin)
            ->put(route('dashboard.admin.users.update', $user), [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role_ids' => [$adminRole->id],
            ])
            ->assertRedirect(route('dashboard.admin.users.index'));

        $fresh = $user->fresh();

        $this->assertTrue($fresh->isAdmin());
        $this->assertTrue($fresh->hasPermission('posts.manage'));
    }

    public function test_admin_user_update_revoking_role_invalidates_permission_cache(): void
    {
        $admin = $this->admin();
        $user = $this->admin();
        $userRole = Role::where('slug', 'user')->first();

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->hasPermission('posts.manage'));

        $this->actingAs($admin)
            ->put(route('dashboard.admin.users.update', $user), [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role_ids' => [$userRole->id],
            ])
            ->assertRedirect(route('dashboard.admin.users.index'));

        $fresh = $user->fresh();

        $this->assertFalse($fresh->isAdmin());
        $this->assertFalse($fresh->hasPermission('posts.manage'));
    }
}
