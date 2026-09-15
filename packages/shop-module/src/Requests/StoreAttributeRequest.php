<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'display_type' => 'sometimes|required|in:text,color,image,button',
        ];
    }
}
