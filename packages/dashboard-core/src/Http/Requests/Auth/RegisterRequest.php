<?php

namespace Nreljed\DashboardCore\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Rules\MinPasswordLength;
use Nreljed\DashboardCore\Rules\PasswordComplexity;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'unique:'.User::class,
                'regex:/^[a-zA-Z0-9_.-]+$/',
            ],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults(), new MinPasswordLength, new PasswordComplexity],
        ];
    }
}
