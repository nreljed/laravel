<?php

namespace Nreljed\DashboardCore\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Nreljed\DashboardCore\Rules\MinPasswordLength;
use Nreljed\DashboardCore\Rules\PasswordComplexity;

class UpdateUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => Str::lower(trim((string) $this->input('username'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:60', Rule::unique('users', 'username')->ignore($this->route('user')), 'regex:/^[a-z0-9]+(?:\.[a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['nullable', 'string', Password::defaults(), new MinPasswordLength, new PasswordComplexity],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'The username may only contain lowercase letters, numbers, and periods (no spaces or other symbols).',
        ];
    }
}
