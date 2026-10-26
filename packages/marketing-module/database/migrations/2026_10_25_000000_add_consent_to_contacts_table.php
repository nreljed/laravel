<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('email_consent_at')->nullable()->after('email');
            $table->timestamp('unsubscribed_at')->nullable()->after('email_consent_at');
            $table->timestamp('suppressed_at')->nullable()->after('unsubscribed_at');
            $table->unsignedInteger('bounce_count')->default(0)->after('suppressed_at');
            $table->timestamp('last_bounced_at')->nullable()->after('bounce_count');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['email_consent_at', 'unsubscribed_at', 'suppressed_at', 'bounce_count', 'last_bounced_at']);
        });
    }
};
