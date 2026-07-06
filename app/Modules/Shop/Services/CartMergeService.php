<?php

namespace App\Modules\Shop\Services;

use App\Models\User;
use App\Modules\Shop\Models\Cart;
use Illuminate\Validation\ValidationException;

class CartMergeService
{
    public function __construct(
        protected CouponService $couponService,
    ) {}

    public function mergeGuestCartIntoUserCart(User $user): void
    {
        $sessionId = session()->getId();
        if (! $sessionId) {
            return;
        }

        $sessionCart = Cart::where('session_id', $sessionId)->first();
        if (! $sessionCart || $sessionCart->items->isEmpty()) {
            return;
        }

        $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

        foreach ($sessionCart->items as $item) {
            $existing = $userCart->items()
                ->where('product_id', $item->product_id)
                ->where('variant_label', $item->variant_label)
                ->first();

            if ($existing) {
                $existing->increment('quantity', $item->quantity);
                $item->delete();
            } else {
                $item->update(['cart_id' => $userCart->id]);
            }
        }

        if ($sessionCart->coupon_code) {
            try {
                $this->couponService->validateAndApply(
                    $sessionCart->coupon_code,
                    $userCart->subtotal,
                    $user->id,
                );
                $userCart->update([
                    'coupon_code' => $sessionCart->coupon_code,
                    'discount' => $sessionCart->discount,
                ]);
            } catch (ValidationException) {
                // Coupon no longer valid for merged cart
            }
        }

        $sessionCart->delete();
    }
}
