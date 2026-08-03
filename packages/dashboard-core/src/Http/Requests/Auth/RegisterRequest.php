<?php

namespace Nreljed\DashboardCore\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Rules\MinPasswordLength;
use Nreljed\DashboardCore\Rules\PasswordComplexity;

class RegisterRequest extends FormRequest
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
            'username' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'unique:'.User::class,
                'regex:/^[a-z0-9]+(?:\.[a-z0-9]+)*$/',
            ],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults(), new MinPasswordLength, new PasswordComplexity],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'The username may only contain lowercase letters, numbers, and periods (no spaces or other symbols).',
        ];
    }
}
