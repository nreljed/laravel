<?php

namespace App\Modules\Shop\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $coupon = $this->route('coupon');
        $couponId = $coupon?->id ?? 'NULL';

        return [
            'code' => 'required|string|max:50|unique:coupons,code,'.$couponId,
            'type' => 'required|in:percentage,fixed,free_shipping',
            'value' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'per_user_limit' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_automatic' => 'boolean',
            'auto_discount_label' => 'nullable|string|max:255',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ];
    }
}
