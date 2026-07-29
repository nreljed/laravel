<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\MinPasswordLength;
use App\Rules\PasswordComplexity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

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
