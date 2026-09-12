<?php

namespace Tests\Feature\Services;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Enums\AuditAction;
use Nreljed\Platform\Models\AuditLog;
use Nreljed\Platform\Services\AuditService;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    public function test_log_with_user_zero_records_null_user_id(): void
    {
        AuditService::log(AuditAction::DatabaseReset, 0, ['with_seed' => true]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DatabaseReset->value,
            'user_id' => null,
        ]);
    }

    public function test_log_with_real_user_records_user_id(): void
    {
        $user = $this->regularUser();

        AuditService::log(AuditAction::Login, $user->id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Login->value,
            'user_id' => $user->id,
        ]);

        $this->assertSame($user->id, AuditLog::latest('id')->first()->user_id);
    }
}
