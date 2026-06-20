<?php

namespace App\Traits;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationTrait
{
    protected function passwordRules(bool $requireCurrent = true): array
    {
        $rules = [];

        if ($requireCurrent) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $rules['password'] = ['required', Password::defaults(), 'confirmed'];

        return $rules;
    }
}
