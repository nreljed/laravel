<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('notes');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone')->nullable()->after('guest_email');
            $table->dateTime('check_in')->nullable()->after('guest_phone');
            $table->dateTime('check_out')->nullable()->after('check_in');
            $table->decimal('nightly_rate', 12, 2)->nullable()->after('check_out');
            $table->decimal('cleaning_fee', 12, 2)->nullable()->after('nightly_rate');
            $table->decimal('service_fee', 12, 2)->nullable()->after('cleaning_fee');
            $table->decimal('damage_deposit', 12, 2)->nullable()->after('service_fee');
            $table->integer('adults')->default(1)->after('damage_deposit');
            $table->integer('children')->default(0)->after('adults');
            $table->integer('pets')->default(0)->after('children');
            $table->string('source', 50)->default('direct')->after('pets');
            $table->text('cancellation_reason')->nullable()->after('source');
            $table->timestamp('confirmed_at')->nullable()->after('cancellation_reason');
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            $table->timestamp('checked_in_at')->nullable()->after('cancelled_at');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'guest_name', 'guest_email', 'guest_phone', 'check_in', 'check_out',
                'nightly_rate', 'cleaning_fee', 'service_fee', 'damage_deposit',
                'adults', 'children', 'pets', 'source', 'cancellation_reason',
                'confirmed_at', 'cancelled_at', 'checked_in_at', 'checked_out_at',
            ]);
        });
    }
};
