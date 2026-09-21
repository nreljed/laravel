<?php

namespace Nreljed\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Nreljed\Platform\Models\Setting;
use Nreljed\Platform\Services\EmailSettingsService;

/**
 * Seeds the platform baseline settings.
 *
 * This is the single canonical source for default settings; consuming apps must
 * NOT ship their own SettingsSeeder. App identity (name, description, URL) and
 * divergence points (default registration role, mail defaults) are resolved
 * from config at seed time:
 *
 *   - app_name / app_description / app_url   → config('app.*')
 *   - default_role                           → config('roles.default_role', 'editor')
 *   - email defaults (JSON email_settings)   → config('mail.default' + 'mail.from.*')
 *
 * `firstOrCreate` keeps any admin-modified value intact, so re-running is safe.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->baseline() as $entry) {
            Setting::firstOrCreate(
                ['key' => $entry['key']],
                ['value' => $entry['value'], 'group' => $entry['group']],
            );
        }
    }

    /**
     * @return list<array{key: string, value: string, group: string}>
     */
    protected function baseline(): array
    {
        $defaults = EmailSettingsService::defaults();

        return [
            // General (includes locale)
            ['key' => 'app_name', 'value' => config('app.name', 'Nreljed Platform'), 'group' => 'general'],
            ['key' => 'app_description', 'value' => config('app.description', ''), 'group' => 'general'],
            ['key' => 'app_url', 'value' => config('app.url', ''), 'group' => 'general'],
            ['key' => 'app_logo', 'value' => '', 'group' => 'general'],
            ['key' => 'app_favicon', 'value' => '', 'group' => 'general'],
            ['key' => 'locale', 'value' => config('app.locale', 'en'), 'group' => 'general'],

            // Registration
            ['key' => 'enable_registration', 'value' => '1', 'group' => 'registration'],
            ['key' => 'default_role', 'value' => config('roles.default_role', 'editor'), 'group' => 'registration'],
            ['key' => 'require_email_verification', 'value' => '0', 'group' => 'registration'],

            // Notifications
            ['key' => 'default_notification_channel', 'value' => 'database', 'group' => 'notifications'],
            ['key' => 'email_notifications_enabled', 'value' => '0', 'group' => 'notifications'],

            // Security
            ['key' => 'password_min_length', 'value' => '8', 'group' => 'security'],
            ['key' => 'password_require_uppercase', 'value' => '0', 'group' => 'security'],
            ['key' => 'password_require_numbers', 'value' => '0', 'group' => 'security'],
            ['key' => 'password_require_special', 'value' => '0', 'group' => 'security'],
            ['key' => 'session_lifetime', 'value' => '120', 'group' => 'security'],
            ['key' => 'max_login_attempts', 'value' => '5', 'group' => 'security'],
            ['key' => 'lockout_duration', 'value' => '5', 'group' => 'security'],

            // Email — single JSON row, core EmailSettingsService shape. SMTP
            // fields stay empty → MailerConfigurator falls back to .env until
            // SMTP is configured via the admin UI.
            ['key' => 'email_settings', 'value' => json_encode(array_merge($defaults, [
                'mailer' => config('mail.default', 'smtp'),
                'from_email' => config('mail.from.address', ''),
                'from_name' => config('mail.from.name', config('app.name', '')),
            ]), JSON_UNESCAPED_SLASHES), 'group' => 'email'],

            // Maintenance
            ['key' => 'maintenance_mode', 'value' => '0', 'group' => 'maintenance'],
            ['key' => 'maintenance_message', 'value' => 'We are performing scheduled maintenance. Please check back soon.', 'group' => 'maintenance'],
            ['key' => 'maintenance_ends_at', 'value' => '', 'group' => 'maintenance'],

            // Formatting (includes date/time/timezone)
            ['key' => 'default_currency', 'value' => 'USD', 'group' => 'formatting'],
            ['key' => 'currency_position', 'value' => 'before', 'group' => 'formatting'],
            ['key' => 'price_empty_text', 'value' => '', 'group' => 'formatting'],
            ['key' => 'surface_unit', 'value' => 'sq_ft', 'group' => 'formatting'],
            ['key' => 'thousands_separator', 'value' => ',', 'group' => 'formatting'],
            ['key' => 'decimal_separator', 'value' => '.', 'group' => 'formatting'],
            ['key' => 'date_format', 'value' => 'Y-m-d', 'group' => 'formatting'],
            ['key' => 'time_format', 'value' => 'H:i', 'group' => 'formatting'],
            ['key' => 'start_of_week', 'value' => '0', 'group' => 'formatting'],
            ['key' => 'timezone', 'value' => config('app.timezone', 'UTC'), 'group' => 'formatting'],

            // Audit Logs
            ['key' => 'audit_logs.retention_days', 'value' => '90', 'group' => 'audit_logs'],
            ['key' => 'audit_logs.default_per_page', 'value' => '50', 'group' => 'audit_logs'],
        ];
    }
}
