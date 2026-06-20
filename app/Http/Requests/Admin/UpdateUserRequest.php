<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:60', Rule::unique('users', 'username')->ignore($this->route('user')), 'regex:/^[a-zA-Z0-9_.-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['nullable', 'exists:roles,id'],
        ];
    }
}
