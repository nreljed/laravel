<?php

namespace App\Http\Requests\Auth;

use App\Rules\MinPasswordLength;
use App\Rules\PasswordComplexity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), new MinPasswordLength, new PasswordComplexity, 'confirmed'],
        ];
    }
}
