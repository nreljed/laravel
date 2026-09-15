<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'countries' => 'nullable|array',
            'countries.*' => 'string|size:2',
            'regions' => 'nullable|array',
            'is_active' => 'boolean',
            'method_ids' => 'nullable|array',
            'method_ids.*.id' => 'exists:shipping_methods,id',
            'method_ids.*.override_price' => 'nullable|numeric|min:0',
        ];
    }
}
