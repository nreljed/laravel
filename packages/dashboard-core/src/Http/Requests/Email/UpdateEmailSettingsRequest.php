<?php

namespace Nreljed\DashboardCore\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mailer' => 'nullable|string|in:smtp,sendmail,mail,log',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'nullable|string|in:tls,ssl,none',
            'smtp_auth' => 'nullable|boolean',
            'auto_tls' => 'nullable|boolean',
            'smtp_allow_self_signed' => 'nullable|boolean',
            'smtp_ehlo_domain' => 'nullable|string|max:255',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'from_name' => 'nullable|string|max:255',
            'reply_to_email' => 'nullable|email|max:255',
            'return_path' => 'nullable|email|max:255',
            'use_from_as_return_path' => 'nullable|boolean',
            'force_from_email' => 'nullable|boolean',
            'force_from_name' => 'nullable|boolean',
        ];
    }
}
