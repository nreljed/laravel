<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'swatch_value' => 'nullable|string|max:500',
        ];
    }
}
