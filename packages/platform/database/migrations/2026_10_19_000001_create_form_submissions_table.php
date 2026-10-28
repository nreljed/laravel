<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One table for every public form.
 *
 * `form_key` discriminates the form, `name`/`email`/`summary` are denormalised
 * from the registry's identity mapping so the inbox list, search and
 * DSAR-by-email lookups stay indexed, and the complete field payload lives in
 * `payload` as JSON. Adding a form therefore costs a config entry, not a table.
 *
 * Existing contact_submissions rows are copied across (form_key = 'contact')
 * with their payload reconstructed from the legacy columns. The old table is
 * dropped by a later release once the new one has been proven in production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('form_key', 64);
            $table->string('name', 190)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('summary', 190)->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('new');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('referrer', 512)->nullable();
            $table->string('utm_source', 128)->nullable();
            $table->string('utm_medium', 128)->nullable();
            $table->string('utm_campaign', 128)->nullable();
            $table->string('utm_term', 128)->nullable();
            $table->string('utm_content', 128)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
            $table->index('email');
            $table->index(['form_key', 'status']);
            $table->index(['form_key', 'created_at']);
        });

        $this->migrateContactRows();
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }

    /**
     * Carry the live contact inbox into the generic table. Skipped on a fresh
     * install, where the legacy table was never created.
     */
    protected function migrateContactRows(): void
    {
        if (! Schema::hasTable('contact_submissions')) {
            return;
        }

        DB::table('contact_submissions')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                $now = now();

                $inserts = $rows->map(function (object $row) use ($now) {
                    return [
                        'form_key' => 'contact',
                        'name' => $row->name,
                        'email' => $row->email,
                        'summary' => $row->subject,
                        'payload' => json_encode([
                            'name' => $row->name,
                            'email' => $row->email,
                            'subject' => $row->subject,
                            'message' => $row->message,
                        ], JSON_UNESCAPED_UNICODE),
                        'status' => $row->status,
                        'ip' => $row->ip,
                        'user_agent' => $row->user_agent,
                        'read_at' => $row->read_at,
                        'created_at' => $row->created_at ?? $now,
                        'updated_at' => $row->updated_at ?? $now,
                    ];
                })->all();

                DB::table('form_submissions')->insert($inserts);
            });
    }
};
