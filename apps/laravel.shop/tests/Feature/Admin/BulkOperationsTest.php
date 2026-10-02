<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\Role;
use Tests\TestCase;

class BulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function admin(): User
    {
        $user = User::factory()->create();
        $role = Role::where('slug', 'admin')->first();
        $user->roles()->attach($role);

        return $user;
    }

    public function test_admin_can_bulk_delete_users(): void
    {
        $admin = $this->admin();
        $users = User::factory()->count(3)->create();
        $ids = $users->pluck('id')->toArray();

        $response = $this->actingAs($admin)
            ->post(route('dashboard.admin.users.bulk.delete'), [
                'ids' => $ids,
            ]);

        $response->assertRedirect();

        foreach ($ids as $id) {
            $this->assertNull(User::find($id), "User {$id} was not deleted");
        }
    }

    public function test_admin_cannot_delete_self_in_bulk(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('dashboard.admin.users.bulk.delete'), [
                'ids' => [$admin->id, $user->id],
            ]);

        $response->assertRedirect();
        $this->assertNotNull($admin->fresh());
        $this->assertNull($user->fresh());
    }

    public function test_admin_can_export_users_csv(): void
    {
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.users.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment; filename="users-', $response->headers->get('Content-Disposition'));
    }
}
