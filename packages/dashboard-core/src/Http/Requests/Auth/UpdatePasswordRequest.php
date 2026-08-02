<?php

namespace Nreljed\DashboardCore\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Nreljed\DashboardCore\Rules\MinPasswordLength;
use Nreljed\DashboardCore\Rules\PasswordComplexity;

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
