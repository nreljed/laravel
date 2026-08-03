<?php

namespace Tests\Feature\Admin;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Nreljed\DashboardCore\Models\User;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class BulkOperationsTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
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

    public function test_bulk_delete_removes_avatar_files(): void
    {
        Storage::fake('avatars');
        $admin = $this->admin();
        $userA = User::factory()->create(['avatar' => 'avatar-a.webp']);
        $userB = User::factory()->create(['avatar' => 'avatar-b.webp']);

        Storage::disk('avatars')->put('avatar-a.webp', 'content');
        Storage::disk('avatars')->put('avatar-b.webp', 'content');

        $this->actingAs($admin)
            ->post(route('dashboard.admin.users.bulk.delete'), [
                'ids' => [$userA->id, $userB->id],
            ])
            ->assertRedirect();

        $this->assertNull($userA->fresh());
        $this->assertNull($userB->fresh());
        $this->assertFalse(Storage::disk('avatars')->exists('avatar-a.webp'));
        $this->assertFalse(Storage::disk('avatars')->exists('avatar-b.webp'));
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
