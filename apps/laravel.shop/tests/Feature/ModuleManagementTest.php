<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Models\Setting;
use Nreljed\Platform\Services\FeatureSettingsService;
use Tests\TestCase;

class ModuleManagementTest extends TestCase
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
        $user->roles()->attach(Role::where('slug', 'admin')->first());

        return $user;
    }

    protected function customer(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'customer')->first());

        return $user;
    }

    public function test_admin_can_open_modules_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/dashboard/admin/settings/modules')
            ->assertStatus(200);
    }

    public function test_non_admin_cannot_open_modules_page(): void
    {
        $this->actingAs($this->customer())
            ->get('/dashboard/admin/settings/modules')
            ->assertStatus(403);
    }

    public function test_update_persists_nested_modules_payload(): void
    {
        $this->actingAs($this->admin())
            ->put('/dashboard/admin/settings/modules', [
                'modules' => [
                    'public-site' => true,
                    'posts' => true,
                    'shop' => false,
                ],
            ])
            ->assertRedirect();

        $settings = app(FeatureSettingsService::class);

        $this->assertTrue($settings->isEnabled('public-site'));
        $this->assertTrue($settings->isEnabled('posts'));
        $this->assertFalse($settings->isEnabled('shop'));
        $this->assertSame('0', Setting::where('key', 'feature_enabled.shop')->value('value'));
    }

    public function test_update_only_touches_slugs_present_in_payload(): void
    {
        $this->actingAs($this->admin())
            ->put('/dashboard/admin/settings/modules', [
                'modules' => [
                    'public-site' => false,
                ],
            ])
            ->assertRedirect();

        $settings = app(FeatureSettingsService::class);

        $this->assertFalse($settings->isEnabled('public-site'));
        $this->assertTrue($settings->isEnabled('posts'));
        $this->assertTrue($settings->isEnabled('shop'));
    }

    public function test_update_ignores_unknown_slugs(): void
    {
        $this->actingAs($this->admin())
            ->put('/dashboard/admin/settings/modules', [
                'modules' => [
                    'does-not-exist' => true,
                ],
            ])
            ->assertRedirect();

        $this->assertNull(Setting::where('key', 'feature_enabled.does-not-exist')->first());
    }

    public function test_disable_then_reenable_roundtrips(): void
    {
        $settings = app(FeatureSettingsService::class);

        $this->actingAs($this->admin())
            ->put('/dashboard/admin/settings/modules', ['modules' => ['shop' => false]])
            ->assertRedirect();
        $this->assertFalse($settings->isEnabled('shop'));

        $this->actingAs($this->admin())
            ->put('/dashboard/admin/settings/modules', ['modules' => ['shop' => true]])
            ->assertRedirect();
        $this->assertTrue($settings->isEnabled('shop'));
    }
}
