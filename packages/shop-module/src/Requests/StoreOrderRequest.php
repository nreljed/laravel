<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_draft' => 'nullable|boolean',
            'customer_type' => 'required|in:existing,guest',
            'user_id' => 'required_if:customer_type,existing|exists:users,id',
            'customer_name' => 'required_if:customer_type,guest|string|max:255',
            'customer_email' => 'required_if:customer_type,guest|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'billing_address' => 'required|array',
            'billing_address.first_name' => 'required|string|max:255',
            'billing_address.last_name' => 'required|string|max:255',
            'billing_address.address_line1' => 'required|string|max:255',
            'billing_address.address_line2' => 'nullable|string|max:255',
            'billing_address.city' => 'required|string|max:255',
            'billing_address.country' => 'required|string|size:2',
            'billing_address.postcode' => 'nullable|string|max:20',
            'billing_address.state' => 'nullable|string|max:255',
            'billing_address.phone' => 'nullable|string|max:50',
            'shipping_address' => 'nullable|array',
            'shipping_address.first_name' => 'required_with:shipping_address|string|max:255',
            'shipping_address.last_name' => 'required_with:shipping_address|string|max:255',
            'shipping_address.address_line1' => 'required_with:shipping_address|string|max:255',
            'shipping_address.address_line2' => 'nullable|string|max:255',
            'shipping_address.city' => 'required_with:shipping_address|string|max:255',
            'shipping_address.country' => 'required_with:shipping_address|string|size:2',
            'shipping_address.postcode' => 'nullable|string|max:20',
            'shipping_address.state' => 'nullable|string|max:255',
            'shipping_address.phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ];
    }
}
