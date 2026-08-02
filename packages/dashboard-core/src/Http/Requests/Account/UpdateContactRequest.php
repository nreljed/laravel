<?php

namespace Nreljed\DashboardCore\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'phone_numbers' => ['nullable', 'array', 'max:5'],
            'phone_numbers.*.number' => ['required_with:phone_numbers', 'string', 'max:20', 'regex:/^[\d\s\-\+\(\)]+$/'],
            'phone_numbers.*.label' => ['nullable', 'string', 'max:50'],
            'phone_numbers.*.primary' => ['nullable', 'boolean'],
            'social_media' => ['nullable', 'array', 'max:10'],
            'social_media.*.platform' => ['required_with:social_media', 'string', 'max:50'],
            'social_media.*.url' => ['required_with:social_media', 'url', 'max:255'],
            'social_media.*.label' => ['nullable', 'string', 'max:50'],
        ];
    }
}
