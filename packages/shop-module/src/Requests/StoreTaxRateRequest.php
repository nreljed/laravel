<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|in:percentage,fixed',
            'country' => 'nullable|string|size:2',
            'state' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
