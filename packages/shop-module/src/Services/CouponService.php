<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Discounts\DiscountManager;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\CouponUsage;

class CouponService
{
    protected DiscountManager $discountManager;

    public function __construct(?DiscountManager $discountManager = null)
    {
        $this->discountManager = $discountManager ?? DiscountManager::defaults();
    }

    public function validateAndApply(string $code, float $subtotal, ?int $userId = null, ?int $customerGroupId = null): Coupon
    {
        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon' => 'Invalid coupon code.']);
        }

        if (! $coupon->isValid($subtotal, $customerGroupId)) {
            if (! $coupon->is_active) {
                throw ValidationException::withMessages(['coupon' => 'This coupon is no longer active.']);
            }
            if ($coupon->starts_at && now()->lt($coupon->starts_at)) {
                throw ValidationException::withMessages(['coupon' => 'This coupon is not yet available.']);
            }
            if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
                throw ValidationException::withMessages(['coupon' => 'This coupon has expired.']);
            }
            if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
                throw ValidationException::withMessages(['coupon' => 'This coupon has reached its usage limit.']);
            }
            if ($coupon->customer_group_id !== null && (int) $coupon->customer_group_id !== (int) $customerGroupId) {
                throw ValidationException::withMessages(['coupon' => 'This coupon is only available to your customer group.']);
            }
            if ($coupon->min_order_amount && $subtotal < (float) $coupon->min_order_amount) {
                throw ValidationException::withMessages(['coupon' => 'Minimum order amount of '.number_format((float) $coupon->min_order_amount, 2).' '.app(CurrencyService::class)->defaultCode().' not met.']);
            }
            throw ValidationException::withMessages(['coupon' => 'This coupon cannot be applied.']);
        }

        if ($userId && $coupon->per_user_limit) {
            $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();

            if ($userUsageCount >= $coupon->per_user_limit) {
                throw ValidationException::withMessages(['coupon' => 'You have already used this coupon the maximum number of times.']);
            }
        }

        return $coupon;
    }

    public function calculateDiscount(Coupon $coupon, float $subtotal, ?float $shippingCost = null, ?Collection $lineItems = null): float
    {
        $driver = $this->discountManager->driver($coupon->type);

        return $driver
            ? $driver->calculate($coupon, $subtotal, $shippingCost, $lineItems)
            : $coupon->calculateDiscount($subtotal, $shippingCost, $lineItems);
    }

    public function getAutoDiscounts(float $subtotal, ?int $customerGroupId = null): Collection
    {
        return Coupon::where('is_automatic', true)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) use ($subtotal) {
                $q->whereNull('min_order_amount')
                    ->orWhere('min_order_amount', '<=', $subtotal)
                    ->orWhere('type', 'free_shipping');
            })
            ->where(function ($q) use ($customerGroupId) {
                $q->whereNull('customer_group_id');

                if ($customerGroupId !== null) {
                    $q->orWhere('customer_group_id', $customerGroupId);
                }
            })
            ->get()
            ->filter(fn (Coupon $coupon) => $coupon->isValid($subtotal, $customerGroupId));
    }

    public function validateStack(Coupon $newCoupon, array $existingCoupons, float $subtotal): void
    {
        foreach ($existingCoupons as $existing) {
            $existingModel = $existing instanceof Coupon ? $existing : Coupon::find($existing['coupon_id'] ?? $existing['id'] ?? null);
            if (! $existingModel) {
                continue;
            }

            if ($existingModel->id === $newCoupon->id) {
                throw ValidationException::withMessages(['coupon' => 'This coupon is already applied.']);
            }

            if ($existingModel->type === 'free_shipping' && $newCoupon->type === 'free_shipping') {
                throw ValidationException::withMessages(['coupon' => 'Only one free shipping coupon can be applied.']);
            }
        }
    }

    public function calculateStackedDiscount(Collection $coupons, float $subtotal, ?float $shippingCost = null, ?Collection $lineItems = null): array
    {
        $runningSubtotal = $subtotal;
        $totalDiscount = 0;
        $breakdown = [];

        foreach ($coupons as $coupon) {
            $driver = $this->discountManager->driver($coupon->type);

            $discount = $driver
                ? $driver->calculate($coupon, $runningSubtotal, $shippingCost, $lineItems)
                : $coupon->calculateDiscount($runningSubtotal, $shippingCost, $lineItems);

            // Subtotal-scoped coupons reduce the running subtotal; line-item-scoped
            // coupons (BOGOF, product-specific) are independent of the remainder.
            if ($driver !== null && $driver->reducesSubtotal()) {
                $runningSubtotal = max(0, $runningSubtotal - $discount);
            }

            $totalDiscount += $discount;
            $breakdown[] = [
                'coupon_id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'discount' => $discount,
            ];
        }

        return [
            'total_discount' => $totalDiscount,
            'breakdown' => $breakdown,
        ];
    }

    public function recordUsage(Coupon $coupon, int $orderId, ?int $userId, float $discountAmount): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $orderId,
            'user_id' => $userId,
            'discount_amount' => $discountAmount,
        ]);

        $coupon->increment('used_count');
    }
}
