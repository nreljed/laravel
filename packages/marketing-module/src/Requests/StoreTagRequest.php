<?php

namespace Nreljed\MarketingModule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTagRequest extends FormRequest
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
            'name' => 'required|string|max:191',
            'slug' => [
                'nullable',
                'string',
                'max:191',
                Rule::unique('tags', 'slug')->ignore($this->route('record')),
            ],
            'color' => 'nullable|string|max:32',
        ];
    }
}
