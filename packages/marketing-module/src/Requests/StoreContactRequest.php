<?php

namespace Nreljed\MarketingModule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nreljed\MarketingModule\Enums\ContactStatus;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('contacts', 'email')->ignore($this->route('record')),
            ],
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:64',
            'status' => ['nullable', Rule::enum(ContactStatus::class)],
            'source' => 'nullable|string|max:64',
        ];
    }
}
