<?php

namespace Tests\Feature\Performance;

use App\Services\DashboardService;
use App\Services\SettingsService;
use Database\Factories\PostFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class DatabasePerformanceTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_dashboard_stats_are_cached_after_first_call(): void
    {
        $service = app(DashboardService::class);

        $this->assertGreaterThan(0, $this->captureQueryCount(fn () => $service->getStats()));

        $this->assertQueryCount(0, fn () => $service->getStats());
    }

    public function test_dashboard_analytics_are_cached_after_first_call(): void
    {
        $service = app(DashboardService::class);

        $this->assertGreaterThan(0, $this->captureQueryCount(fn () => $service->getAnalytics()));

        $this->assertQueryCount(0, fn () => $service->getAnalytics());
    }

    public function test_clear_stats_cache_forces_recomputation(): void
    {
        $service = app(DashboardService::class);

        $service->getStats();
        $service->clearStatsCache();

        $this->assertGreaterThan(0, $this->captureQueryCount(fn () => $service->getStats()));
    }

    public function test_settings_group_is_cached_after_first_call(): void
    {
        $this->assertGreaterThan(0, $this->captureQueryCount(fn () => SettingsService::getGroup('formatting')));

        $this->assertQueryCount(0, fn () => SettingsService::getGroup('formatting'));
    }

    public function test_setting_value_is_cached_and_invalidated_on_write(): void
    {
        $this->assertGreaterThan(0, $this->captureQueryCount(fn () => SettingsService::get('maintenance_mode', '0')));

        $this->assertQueryCount(0, fn () => SettingsService::get('maintenance_mode', '0'));

        SettingsService::set('maintenance_mode', '1');

        $this->assertEquals('1', SettingsService::get('maintenance_mode', '0'));
    }

    public function test_posts_index_query_count_is_constant_across_post_count(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->get(route('dashboard.posts.index'))->assertOk();

        PostFactory::new()->create(['user_id' => $admin->id]);

        $withOnePost = $this->captureQueryCount(function () {
            $this->get(route('dashboard.posts.index'))->assertOk();
        });

        PostFactory::new()->count(4)->create(['user_id' => $admin->id]);

        $withFivePosts = $this->captureQueryCount(function () {
            $this->get(route('dashboard.posts.index'))->assertOk();
        });

        $this->assertSame(
            $withOnePost,
            $withFivePosts,
            'Post index query count grew with row count — possible N+1 query.'
        );
    }
}
