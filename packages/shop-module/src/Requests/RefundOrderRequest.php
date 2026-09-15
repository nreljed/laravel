<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('refund', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.amount' => 'required|numeric|min:0',
            'shipping_refund' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
        ];
    }
}
