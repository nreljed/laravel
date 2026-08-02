<?php

namespace Nreljed\DashboardCore\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', 'string'],
            'preferences.*.channel' => ['required', 'string', 'in:in_app,email'],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }
}
