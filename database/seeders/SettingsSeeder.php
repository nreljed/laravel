<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'app_name', 'value' => 'Cost Breaker 360', 'group' => 'general'],
            ['key' => 'app_description', 'value' => 'SaaS application for cost management', 'group' => 'general'],
            ['key' => 'app_url', 'value' => config('app.url'), 'group' => 'general'],
            ['key' => 'app_logo', 'value' => '', 'group' => 'general'],
            ['key' => 'timezone', 'value' => 'UTC', 'group' => 'general'],

            // Registration
            ['key' => 'enable_registration', 'value' => '1', 'group' => 'registration'],
            ['key' => 'default_role', 'value' => 'crm_user', 'group' => 'registration'],
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
            ['key' => 'mail_from_name', 'value' => 'Cost Breaker 360', 'group' => 'email'],

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

            // Properties (Map)
            ['key' => 'map_country_code', 'value' => 'TN', 'group' => 'properties'],
            ['key' => 'map_bounds_south_west_lat', 'value' => '30.24', 'group' => 'properties'],
            ['key' => 'map_bounds_south_west_lng', 'value' => '7.52', 'group' => 'properties'],
            ['key' => 'map_bounds_north_east_lat', 'value' => '37.54', 'group' => 'properties'],
            ['key' => 'map_bounds_north_east_lng', 'value' => '11.6', 'group' => 'properties'],
            ['key' => 'map_center_lat', 'value' => '34.0', 'group' => 'properties'],
            ['key' => 'map_center_lng', 'value' => '9.5', 'group' => 'properties'],
            ['key' => 'map_min_zoom', 'value' => '6', 'group' => 'properties'],
            ['key' => 'map_max_zoom', 'value' => '18', 'group' => 'properties'],
            ['key' => 'map_default_zoom', 'value' => '7', 'group' => 'properties'],
            ['key' => 'map_marker_zoom', 'value' => '18', 'group' => 'properties'],
            ['key' => 'map_debug_show', 'value' => '0', 'group' => 'properties'],
            ['key' => 'enable_property_reviews', 'value' => '1', 'group' => 'properties'],
            ['key' => 'require_approval', 'value' => '0', 'group' => 'properties'],

            // Booking
            ['key' => 'booking_check_in_time', 'value' => '15:00', 'group' => 'booking'],
            ['key' => 'booking_check_out_time', 'value' => '11:00', 'group' => 'booking'],
            ['key' => 'booking_min_nights', 'value' => '1', 'group' => 'booking'],
            ['key' => 'booking_default_cleaning_fee', 'value' => '50', 'group' => 'booking'],
            ['key' => 'booking_service_fee_percent', 'value' => '5', 'group' => 'booking'],
            ['key' => 'booking_default_damage_deposit', 'value' => '200', 'group' => 'booking'],
            ['key' => 'booking_auto_checkout_hours', 'value' => '24', 'group' => 'booking'],
            ['key' => 'booking_no_show_days', 'value' => '2', 'group' => 'booking'],
            ['key' => 'booking_notify_on_create', 'value' => '1', 'group' => 'booking'],
            ['key' => 'booking_notify_on_cancel', 'value' => '1', 'group' => 'booking'],
            ['key' => 'booking_auto_confirm', 'value' => '0', 'group' => 'booking'],
        ];

        foreach ($settings as $s) {
            Setting::firstOrCreate(['key' => $s['key']], $s);
        }
    }
}
