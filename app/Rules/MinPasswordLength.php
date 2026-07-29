<?php

namespace App\Rules;

use App\Services\SettingsService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

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
