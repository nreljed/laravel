<?php

namespace App\Http\Requests\Admin;

use App\Rules\MinPasswordLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:60', Rule::unique('users', 'username')->ignore($this->route('user')), 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['nullable', 'string', Password::defaults(), new MinPasswordLength],
            'role_id' => ['nullable', 'exists:roles,id'],
        ];
    }
}
