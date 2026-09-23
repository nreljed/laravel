<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Services\CouponService;

class RecordCouponUsage
{
    public function __construct(protected CouponService $coupons) {}

    public function handle(CheckoutContext $context, Closure $next)
    {
        foreach ($context->quote['coupons'] as $idx => $coupon) {
            $this->coupons->recordUsage(
                $coupon,
                $context->order->id,
                $context->user?->id,
                $context->quote['discount_breakdown'][$idx]['discount'] ?? 0,
            );
        }

        return $next($context);
    }
}