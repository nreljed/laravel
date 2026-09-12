<?php

use Illuminate\Database\Migrations\Migration;
use Nreljed\Platform\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Previously renamed user → crm_user; now reverted to user
        // This migration is kept for history; the current naming is 'user'
    }

    public function down(): void
    {
        // No-op: role naming is now 'user', down() mirrors no-op up()
    }
};
