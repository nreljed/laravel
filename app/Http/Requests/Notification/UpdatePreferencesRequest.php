<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
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
