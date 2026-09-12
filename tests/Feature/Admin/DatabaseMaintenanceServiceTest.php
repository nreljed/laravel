<?php

namespace Tests\Feature\Admin;

use Database\Seeders\BlogContentSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Exceptions\DatabaseMaintenanceException;
use Nreljed\Platform\Services\DatabaseMaintenanceService;
use Tests\TestCase;

class DatabaseMaintenanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        @unlink(base_path('.maintenance-lock'));
    }

    protected function tearDown(): void
    {
        @unlink(base_path('.maintenance-lock'));

        parent::tearDown();
    }

    private function serviceWithRecorder(array &$commands): DatabaseMaintenanceService
    {
        return new DatabaseMaintenanceService(function (string $command, array $params = []) use (&$commands) {
            $commands[] = ['command' => $command, 'params' => $params];

            return 0;
        });
    }

    public function test_reset_runs_baseline_without_demo_content(): void
    {
        // v0.1.17: create-admin only runs when admin env is configured.
        config([
            'admin.email' => 'admin@example.com',
            'admin.password' => 'secret-password',
        ]);

        $commands = [];
        $result = $this->serviceWithRecorder($commands)->resetDatabase();

        $this->assertTrue($result['success']);

        $fresh = collect($commands)->first(fn ($entry) => $entry['command'] === 'migrate:fresh');
        $this->assertNotNull($fresh);
        $this->assertArrayNotHasKey('--seed', $fresh['params']);

        $classes = collect($commands)
            ->where('command', 'db:seed')
            ->pluck('params.--class')
            ->all();

        $this->assertContains(RolesAndPermissionsSeeder::class, $classes);
        $this->assertContains(SettingsSeeder::class, $classes);
        $this->assertNotContains(DemoUsersSeeder::class, $classes);
        $this->assertNotContains(BlogContentSeeder::class, $classes);

        $this->assertContains(
            'app:create-admin',
            collect($commands)->pluck('command')->all(),
        );
    }

    public function test_reset_skips_create_admin_without_admin_env(): void
    {
        config(['admin.email' => null, 'admin.password' => null]);

        $commands = [];
        $output = [];
        $service = new DatabaseMaintenanceService(function (string $command, array $params = []) use (&$commands) {
            $commands[] = ['command' => $command, 'params' => $params];

            return 0;
        });

        $result = $service->resetDatabase();

        $this->assertTrue($result['success']);
        $this->assertEmpty(collect($commands)->where('command', 'app:create-admin'));
        $this->assertStringContainsString(
            'app:create-admin skipped',
            implode("\n", $result['output']),
        );
    }

    public function test_reset_with_demo_includes_demo_seeders(): void
    {
        $commands = [];
        $result = $this->serviceWithRecorder($commands)->resetDatabase(demo: true);

        $this->assertTrue($result['success']);

        $classes = collect($commands)
            ->where('command', 'db:seed')
            ->pluck('params.--class')
            ->all();

        $this->assertContains(RolesAndPermissionsSeeder::class, $classes);
        $this->assertContains(SettingsSeeder::class, $classes);
        $this->assertContains(DemoUsersSeeder::class, $classes);
        $this->assertContains(BlogContentSeeder::class, $classes);
    }

    public function test_reset_fails_when_locked(): void
    {
        file_put_contents(base_path('.maintenance-lock'), (string) getmypid());

        $this->expectException(DatabaseMaintenanceException::class);

        $commands = [];
        $this->serviceWithRecorder($commands)->resetDatabase();
    }

    public function test_reseed_runs_only_selected_groups(): void
    {
        $commands = [];
        $result = $this->serviceWithRecorder($commands)->reseedDatabase(['roles', 'blog']);

        $this->assertTrue($result['success']);

        $classes = collect($commands)
            ->where('command', 'db:seed')
            ->pluck('params.--class')
            ->all();

        $this->assertSame([RolesAndPermissionsSeeder::class, BlogContentSeeder::class], $classes);
        $this->assertEmpty(collect($commands)->where('command', 'migrate:fresh'));
        $this->assertEmpty(collect($commands)->where('command', 'app:create-admin'));
    }

    public function test_reseed_rejects_unknown_group(): void
    {
        $this->expectException(DatabaseMaintenanceException::class);
        $this->expectExceptionMessage('Unknown seeder group');

        $commands = [];
        $this->serviceWithRecorder($commands)->reseedDatabase(['nope']);
    }

    public function test_reseed_rejects_empty_selection(): void
    {
        $this->expectException(DatabaseMaintenanceException::class);
        $this->expectExceptionMessage('No seeders were selected');

        $commands = [];
        $this->serviceWithRecorder($commands)->reseedDatabase([]);
    }

    public function test_status_exposes_reseed_groups(): void
    {
        $status = app(DatabaseMaintenanceService::class)->status();

        $this->assertArrayHasKey('reseed_groups', $status);

        $keys = array_column($status['reseed_groups'], 'key');
        $this->assertSame(['roles', 'settings', 'demo_users', 'blog'], $keys);

        $blog = collect($status['reseed_groups'])->firstWhere('key', 'blog');
        $this->assertTrue($blog['destructive']);

        $roles = collect($status['reseed_groups'])->firstWhere('key', 'roles');
        $this->assertFalse($roles['destructive']);
    }
}
