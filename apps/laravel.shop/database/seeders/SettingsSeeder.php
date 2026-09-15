<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Nreljed\Platform\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'app_name', 'value' => 'Shop App', 'group' => 'general'],
            ['key' => 'app_description', 'value' => 'SaaS application for cost management', 'group' => 'general'],
            ['key' => 'app_url', 'value' => config('app.url'), 'group' => 'general'],
            ['key' => 'app_logo', 'value' => '', 'group' => 'general'],
            ['key' => 'timezone', 'value' => 'UTC', 'group' => 'general'],

            // Registration
            ['key' => 'enable_registration', 'value' => '1', 'group' => 'registration'],
            ['key' => 'default_role', 'value' => 'customer', 'group' => 'registration'],
            ['key' => 'require_email_verification', 'value' => '0', 'group' => 'registration'],

            // Notifications
            ['key' => 'default_notification_channel', 'value' => 'database', 'group' => 'notifications'],
            ['key' => 'email_notifications_enabled', 'value' => '0', 'group' => 'notifications'],

            // Security
            ['key' => 'password_min_length', 'value' => '8', 'group' => 'security'],
            ['key' => 'session_lifetime', 'value' => '120', 'group' => 'security'],
            ['key' => 'max_login_attempts', 'value' => '5', 'group' => 'security'],

            // Email
            ['key' => 'mail_driver', 'value' => config('mail.default', 'log'), 'group' => 'email'],
            ['key' => 'mail_from_address', 'value' => 'noreply@costbreaker360.com', 'group' => 'email'],
            ['key' => 'mail_from_name', 'value' => 'Shop App', 'group' => 'email'],

            // Maintenance
            ['key' => 'maintenance_mode', 'value' => '0', 'group' => 'maintenance'],
            ['key' => 'maintenance_message', 'value' => 'We are performing scheduled maintenance. Please check back soon.', 'group' => 'maintenance'],

            // Localization
            ['key' => 'locale', 'value' => 'en', 'group' => 'localization'],
            ['key' => 'date_format', 'value' => 'Y-m-d', 'group' => 'localization'],
            ['key' => 'time_format', 'value' => 'H:i', 'group' => 'localization'],
            ['key' => 'start_of_week', 'value' => '0', 'group' => 'localization'],

            // Formatting
            ['key' => 'default_currency', 'value' => 'USD', 'group' => 'formatting'],
            ['key' => 'currency_position', 'value' => 'before', 'group' => 'formatting'],
            ['key' => 'price_empty_text', 'value' => '', 'group' => 'formatting'],
            ['key' => 'surface_unit', 'value' => 'sq_ft', 'group' => 'formatting'],
            ['key' => 'thousands_separator', 'value' => ',', 'group' => 'formatting'],
            ['key' => 'decimal_separator', 'value' => '.', 'group' => 'formatting'],

        ];

        foreach ($settings as $s) {
            Setting::firstOrCreate(['key' => $s['key']], $s);
        }
    }
}
