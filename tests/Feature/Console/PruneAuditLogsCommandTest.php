<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class PruneAuditLogsCommandTest extends TestCase
{
    use CreatesTestUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        SettingsService::clearCache();
    }

    public function test_deletes_audit_logs_older_than_retention_days(): void
    {
        $user = $this->admin();

        $old = $this->createAuditLog($user, now()->subDays(100));
        $recent = $this->createAuditLog($user, now()->subDay());

        $this->artisan('app:prune-audit-logs')
            ->expectsOutputToContain('Deleted 1 audit log')
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);
    }

    public function test_days_option_overrides_settings(): void
    {
        Setting::create([
            'key' => 'audit_logs.retention_days',
            'value' => '90',
            'group' => 'audit_logs',
        ]);
        SettingsService::clearCache();

        $user = $this->admin();
        $old = $this->createAuditLog($user, now()->subDays(30));

        $this->artisan('app:prune-audit-logs', ['--days' => 7])
            ->assertSuccessful();

        $this->assertDatabaseMissing('audit_logs', ['id' => $old->id]);
    }

    public function test_skips_when_retention_days_below_one(): void
    {
        $user = $this->admin();
        $log = $this->createAuditLog($user, now()->subDays(30));

        $this->artisan('app:prune-audit-logs', ['--days' => 0])
            ->expectsOutputToContain('Retention days must be at least 1')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    private function createAuditLog(User $user, \DateTimeInterface $createdAt): AuditLog
    {
        return AuditLog::create([
            'action' => 'test_action',
            'user_id' => $user->id,
            'ip' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => $createdAt,
        ]);
    }
}
