<?php

namespace Nreljed\DashboardCore\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Nreljed\DashboardCore\Services\SettingsService;

class MinPasswordLength implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $minLength = (int) SettingsService::get('password_min_length', 8);

        if (strlen((string) $value) < $minLength) {
            $fail('validation.min.string')->translate([
                'attribute' => $attribute,
                'min' => $minLength,
            ]);
        }
    }
}
