<?php

namespace Nreljed\DashboardCore\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    private const array KEY_GROUP_MAP = [
        'app_name' => 'general',
        'app_description' => 'general',
        'app_url' => 'general',
        'app_logo' => 'general',
        'app_favicon' => 'general',
        'locale' => 'general',
        'default_currency' => 'formatting',
        'currency_position' => 'formatting',
        'price_empty_text' => 'formatting',
        'default_country' => 'formatting',
        'surface_unit' => 'formatting',
        'thousands_separator' => 'formatting',
        'decimal_separator' => 'formatting',
        'date_format' => 'formatting',
        'time_format' => 'formatting',
        'start_of_week' => 'formatting',
        'timezone' => 'formatting',
        'enable_registration' => 'registration',
        'default_role' => 'registration',
        'require_email_verification' => 'registration',
        'default_notification_channel' => 'notifications',
        'email_notifications_enabled' => 'notifications',
        'password_min_length' => 'security',
        'password_require_uppercase' => 'security',
        'password_require_numbers' => 'security',
        'password_require_special' => 'security',
        'session_lifetime' => 'security',
        'max_login_attempts' => 'security',
        'lockout_duration' => 'security',
        'maintenance_mode' => 'maintenance',
        'maintenance_message' => 'maintenance',
        'maintenance_ends_at' => 'maintenance',
        'maintenance_bypass_token' => 'maintenance',
        'database_maintenance_enabled' => 'developer',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $group = self::KEY_GROUP_MAP[$key] ?? static::where('key', $key)->value('group');
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }
}
