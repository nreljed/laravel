<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->timestamp('sms_consent_at')->nullable()->after('email_consent_at');
            $table->timestamp('whatsapp_consent_at')->nullable()->after('sms_consent_at');
            $table->timestamp('sms_opted_out_at')->nullable()->after('sms_consent_at');
            $table->timestamp('whatsapp_opted_out_at')->nullable()->after('whatsapp_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'sms_consent_at',
                'whatsapp_consent_at',
                'sms_opted_out_at',
                'whatsapp_opted_out_at',
            ]);
        });
    }
};
