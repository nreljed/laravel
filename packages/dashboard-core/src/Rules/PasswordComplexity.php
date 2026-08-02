<?php

namespace Nreljed\DashboardCore\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Nreljed\DashboardCore\Services\SettingsService;

class PasswordComplexity implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        if (SettingsService::get('password_require_uppercase', '0') === '1' && ! preg_match('/[A-Z]/', $password)) {
            $fail('validation.password.uppercase')->translate([
                'attribute' => $attribute,
            ]);
        }

        if (SettingsService::get('password_require_numbers', '0') === '1' && ! preg_match('/[0-9]/', $password)) {
            $fail('validation.password.numbers')->translate([
                'attribute' => $attribute,
            ]);
        }

        if (SettingsService::get('password_require_special', '0') === '1' && ! preg_match('/[^A-Za-z0-9]/', $password)) {
            $fail('validation.password.special')->translate([
                'attribute' => $attribute,
            ]);
        }
    }
}
