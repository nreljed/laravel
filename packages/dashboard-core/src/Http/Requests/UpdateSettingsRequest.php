<?php

namespace Nreljed\DashboardCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        $enableRegistration = $this->input('enable_registration');

        return [
            'app_name' => 'nullable|string|max:255',
            'app_logo' => 'nullable|string|max:500',
            'app_favicon' => 'nullable|string|max:500',
            'app_description' => 'nullable|string|max:500',
            'app_url' => 'nullable|url|max:255',
            'timezone' => 'nullable|string|max:100',
            'enable_registration' => 'nullable|in:0,1',
            'default_role' => [
                $enableRegistration === '1' ? 'required' : 'nullable',
                'string',
                'max:50',
                Rule::exists('roles', 'slug')->where(fn ($q) => $q->where('slug', '!=', 'admin')),
            ],
            'require_email_verification' => 'nullable|in:0,1',
            'default_notification_channel' => 'nullable|string|max:50',
            'email_notifications_enabled' => 'nullable|in:0,1',
            'password_min_length' => 'nullable|integer|min:8|max:128',
            'password_require_uppercase' => 'nullable|in:0,1',
            'password_require_numbers' => 'nullable|in:0,1',
            'password_require_special' => 'nullable|in:0,1',
            'session_lifetime' => 'nullable|integer|min:1|max:43200',
            'max_login_attempts' => 'nullable|integer|min:1|max:100',
            'lockout_duration' => 'nullable|integer|min:1|max:1440',
            'mail_driver' => 'nullable|string|max:50',
            'mail_from_address' => 'nullable|email|max:255',
            'mail_from_name' => 'nullable|string|max:255',
            'maintenance_mode' => 'nullable|in:0,1',
            'maintenance_message' => 'nullable|string|max:1000',
            'maintenance_ends_at' => 'nullable|string|max:50',
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
        ];
    }
}
