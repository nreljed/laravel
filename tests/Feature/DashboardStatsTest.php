<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Nreljed\DashboardCore\Database\Factories\PostFactory;
use Nreljed\DashboardCore\Services\DashboardService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard.stats'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_stats_page_with_kpis(): void
    {
        $this->seedRolesAndPermissions();
        $user = $this->regularUser();
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)
            ->get(route('dashboard.stats'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard/stats')
                ->has('stats')
                ->where('stats.total_users.value', 1)
            );
    }

    public function test_stats_include_feature_module_kpis(): void
    {
        $this->seedRolesAndPermissions();
        $this->regularUser()->forceFill(['email_verified_at' => now()])->save();
        PostFactory::new()->published()->create();

        $stats = app(DashboardService::class)->getStats();

        $this->assertArrayHasKey('posts', $stats);
        $this->assertSame(1, $stats['posts']['value']);
        $this->assertSame(1, $stats['posts_published']['value']);
    }

    public function test_analytics_users_series_covers_last_30_days(): void
    {
        $this->seedRolesAndPermissions();
        $user = $this->regularUser();
        $user->forceFill(['email_verified_at' => now(), 'created_at' => now()->subDays(2)])->save();

        $analytics = app(DashboardService::class)->getAnalytics();

        $this->assertArrayHasKey('users', $analytics);
        $this->assertCount(30, $analytics['users']['data']);
        $this->assertSame(1, $analytics['users']['data'][27]['count']);
        $this->assertSame(0, $analytics['users']['data'][0]['count']);
    }

    public function test_analytics_includes_feature_posts_series(): void
    {
        $this->seedRolesAndPermissions();
        $this->regularUser()->forceFill(['email_verified_at' => now()])->save();
        PostFactory::new()->published()->create();

        $analytics = app(DashboardService::class)->getAnalytics();

        $this->assertArrayHasKey('posts', $analytics);
        $this->assertCount(30, $analytics['posts']['data']);
        $this->assertSame(1, $analytics['posts']['data'][29]['count']);
    }
}
