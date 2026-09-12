<?php

namespace Tests\Feature\Security;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\IpAccessRule;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class IpAccessControlTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_pages_accessible_with_no_rules(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_blocked_ip_returns_403(): void
    {
        IpAccessRule::create(['ip_address' => '127.0.0.1', 'type' => 'block', 'is_active' => true]);
        $response = $this->get('/');
        $response->assertStatus(403);
    }

    public function test_admin_bypasses_ip_block(): void
    {
        IpAccessRule::create(['ip_address' => '127.0.0.1', 'type' => 'block', 'is_active' => true]);
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();
        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_inactive_rule_does_not_block(): void
    {
        IpAccessRule::create(['ip_address' => '127.0.0.1', 'type' => 'block', 'is_active' => false]);
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function test_wildcard_block_matches(): void
    {
        IpAccessRule::create(['ip_address' => '127.*', 'type' => 'block', 'is_active' => true]);
        $response = $this->get('/');
        $response->assertStatus(403);
    }

    public function test_allow_rule_restricts_access(): void
    {
        IpAccessRule::create(['ip_address' => '10.0.0.1', 'type' => 'allow', 'is_active' => true]);
        $response = $this->get('/');
        $response->assertStatus(403);
    }

    public function test_admin_can_manage_ip_rules(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();
        $response = $this->actingAs($admin)->postJson('/dashboard/admin/ip-rules', [
            'ip_address' => '192.168.1.100', 'type' => 'block', 'description' => 'Test',
        ]);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseHas('ip_access_rules', ['ip_address' => '192.168.1.100']);
    }

    public function test_non_admin_cannot_manage_ip_rules(): void
    {
        $user = $this->regularUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $response = $this->actingAs($user)->postJson('/dashboard/admin/ip-rules', [
            'ip_address' => '192.168.1.100', 'type' => 'block',
        ]);
        $response->assertStatus(403);
    }

    public function test_duplicate_rule_is_rejected(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();
        IpAccessRule::create(['ip_address' => '192.168.1.1', 'type' => 'block', 'is_active' => true]);
        $response = $this->actingAs($admin)->postJson('/dashboard/admin/ip-rules', [
            'ip_address' => '192.168.1.1', 'type' => 'block',
        ]);
        $response->assertStatus(422);
    }

    public function test_ip_rule_can_be_deleted(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();
        $rule = IpAccessRule::create(['ip_address' => '10.0.0.99', 'type' => 'block', 'is_active' => true]);
        $response = $this->actingAs($admin)->deleteJson("/dashboard/admin/ip-rules/{$rule->id}");
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('ip_access_rules', ['id' => $rule->id]);
    }
}
