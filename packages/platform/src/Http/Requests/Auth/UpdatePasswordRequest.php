<?php

namespace Nreljed\Platform\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Nreljed\Platform\Rules\MinPasswordLength;
use Nreljed\Platform\Rules\PasswordComplexity;

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
