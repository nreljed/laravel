<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'app_name' => 'nullable|string|max:255',
            'app_logo' => 'nullable|string|max:500',
            'app_description' => 'nullable|string|max:500',
            'app_url' => 'nullable|url|max:255',
            'timezone' => 'nullable|string|max:100',
            'enable_registration' => 'nullable|in:0,1',
            'default_role' => [
                'nullable',
                'string',
                'max:50',
                Rule::exists('roles', 'slug')->where(fn ($q) => $q->where('slug', '!=', 'admin')),
            ],
            'require_email_verification' => 'nullable|in:0,1',
            'default_notification_channel' => 'nullable|string|max:50',
            'email_notifications_enabled' => 'nullable|in:0,1',
            'password_min_length' => 'nullable|integer|min:8|max:128',
            'session_lifetime' => 'nullable|integer|min:1|max:99999',
            'max_login_attempts' => 'nullable|integer|min:1|max:100',
            'mail_driver' => 'nullable|string|max:50',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
            'maintenance_mode' => 'nullable|in:0,1',
            'maintenance_message' => 'nullable|string|max:1000',
            'locale' => 'nullable|string|max:10',
            'date_format' => 'nullable|string|max:50',
            'time_format' => 'nullable|string|max:50',
            'start_of_week' => 'nullable|string|max:1',
            'default_currency' => 'nullable|string|max:5',
            'currency_position' => 'nullable|in:before,after',
            'price_empty_text' => 'nullable|string|max:100',
            'surface_unit' => 'nullable|string|max:20',
            'default_country' => 'nullable|string|size:2',
            'thousands_separator' => 'nullable|string|max:1',
            'decimal_separator' => 'nullable|string|max:1',
            'enable_property_reviews' => 'nullable|boolean',
            'require_approval' => 'nullable|boolean',
            'map_country_code' => 'nullable|string|size:2',
            'map_bounds_south_west_lat' => 'nullable|numeric|between:-90,90',
            'map_bounds_south_west_lng' => 'nullable|numeric|between:-180,180',
            'map_bounds_north_east_lat' => 'nullable|numeric|between:-90,90',
            'map_bounds_north_east_lng' => 'nullable|numeric|between:-180,180',
            'map_center_lat' => 'nullable|numeric|between:-90,90',
            'map_center_lng' => 'nullable|numeric|between:-180,180',
            'map_min_zoom' => 'nullable|integer|min:1|max:20',
            'map_max_zoom' => 'nullable|integer|min:1|max:20',
            'map_default_zoom' => 'nullable|integer|min:1|max:20',
            'map_marker_zoom' => 'nullable|integer|min:1|max:20',
            'map_debug_show' => 'nullable|in:0,1',
        ];
    }
}
