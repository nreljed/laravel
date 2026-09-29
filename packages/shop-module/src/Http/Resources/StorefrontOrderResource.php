<?php

namespace Nreljed\ShopModule\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public storefront's view of an order.
 *
 * `CheckoutController::confirmation()` hands the `Order` model straight to
 * Inertia, which serialises whatever `toArray()` produces, and the model has no
 * `$hidden`. The page is behind `authorizeOrderView()` - owner, staff, the
 * emailed token or a signed link - so this is not a world-readable order, but
 * the payload still carried the columns that are none of the shopper's
 * business:
 *
 *  - `confirmation_token`, the very credential that opens the page, published
 *    into the response of the page it unlocks;
 *  - `payment_intent_id`, a Stripe PaymentIntent identifier belonging in
 *    server-to-server traffic only;
 *  - `notes` and `refund_notes`, free-text staff commentary;
 *  - `processed_by`, the id of the staff user who handled the order;
 *  - `refund_amount`, `refunded_at`, `cart_id`, `paid_at`, `shipped_at` and
 *    `payment_status`, internal workflow state.
 *
 * The access token matters most here: the link is emailed and long-lived, and
 * the page is reachable from a shared or cached machine, so a secret that
 * doubles as a credential has no business being in the rendered payload.
 *
 * The field list is the confirmation page's own contract - `Orders/Confirmation`
 * reads `id`, `order_number`, `payment_method`, `billing_address`,
 * `shipping_address`, `subtotal`, `shipping_total`, `total` and `items`, and
 * nothing else, so trimming the payload is not a UI change.
 *
 * An allowlist rather than a denylist on purpose: a new column on `orders` is
 * private until someone adds it here.
 */
class StorefrontOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'payment_method' => $this->payment_method,

            'subtotal' => (float) $this->subtotal,
            'shipping_total' => (float) $this->shipping_total,
            'total' => (float) $this->total,
            'currency' => $this->currency,

            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,

            'items' => $this->whenLoaded('items', fn () => $this->items
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                    'variant_label' => $item->variant_label,
                ])->values()),

            // Deliberately absent: `confirmation_token`, `payment_intent_id`,
            // `notes`, `refund_notes`, `refund_amount`, `refunded_at`,
            // `processed_by`, `user_id`, `customer_email`, `customer_phone`,
            // `customer_name`, `tax_total`, `discount_total`, `cart_id`,
            // `payment_status`, `paid_at`, `shipped_at`, `applied_coupons` and
            // the timestamps.
            //
            // The customer already knows their own name, email, phone and
            // addresses, so those are not secrets - they are simply not part of
            // this page's contract, and the confirmation is the one place a
            // payload is handed to a token holder rather than to the account.
            //
            // Staff keep the full record: the dashboard's `OrderResource` is a
            // separate class and is unaffected, which is why this is a
            // storefront resource and not a `$hidden` array on the model.
        ];
    }
}
