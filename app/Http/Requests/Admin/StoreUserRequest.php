<?php

namespace App\Http\Requests\Admin;

use App\Rules\MinPasswordLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:60', 'unique:users,username', 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), new MinPasswordLength],
            'role_id' => ['nullable', 'exists:roles,id'],
            'send_email' => ['nullable', 'boolean'],
        ];
    }
}
