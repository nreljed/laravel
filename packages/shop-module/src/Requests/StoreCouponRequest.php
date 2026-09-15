<?php

namespace Nreljed\ShopModule\Requests;

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
            'type' => 'required|in:percentage,fixed,free_shipping,buy_x_get_y,fixed_product,percentage_product,tiered',
            'value' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:0',
            'per_user_limit' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_automatic' => 'boolean',
            'auto_discount_label' => 'nullable|string|max:255',
            'buy_qty' => 'nullable|integer|min:1',
            'free_qty' => 'nullable|integer|min:1',
            'free_product_id' => 'nullable|exists:products,id',
            'tiers' => 'nullable|array',
            'tiers.*.min_qty' => 'required|integer|min:1',
            'tiers.*.discount_percent' => 'required|numeric|min:0.01|max:100',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ];
    }
}
