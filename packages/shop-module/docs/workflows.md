# Workflows

The important state machines and async flows in the Shop domain.

## Order Lifecycle

Statuses (10) managed by `OrderStatusService`:

```
draft ──► pending ──► processing ──► completed
   │        │  ▲          │
   │        │  │          └──► shipped ──► completed
   │        ▼  │
   └──► on-hold ──► processing
pending/processing/completed/shipped ──► cancelled ──► refunded
```

Transitions are validated by `OrderStatusService::allowedTransitions()`; invalid transitions
throw `ValidationException`.

- **Pending** → **Processing** (or auto-completed if `auto_complete_orders` setting is on).
- **Processing** → **Completed**: `InventoryService::recordSaleForOrder()` decrements stock and
  writes `StockAdjustment` rows; `shipped_at` auto-set.
- **Cancelled**: `recordRefundForOrder()` restores stock (respecting `track_stock`).
- **Refund**: full-refund via `OrdersController::refund()` → `recordRefundForOrder()`.
- Each transition fires `order.status_changed` notification + `order.updated` webhook.

## Hold Stock

Fights overselling between "order placed" and "order paid" for track-stock products.

```
Checkout completes (order = pending, unpaid)
        │
        ▼
InventoryService::recordSaleForOrder()  → stock decremented at order time
        │
        ▼
Order unpaid for > hold_stock_minutes
        │
        ▼
ReleaseExpiredHolds (every 5 min)  →  stock restored, order → cancelled
```

- Per-product `hold_stock_minutes` overrides the global `hold_stock.default_minutes` (60).
- The `ReleaseExpiredHolds` command evaluates the minimum hold across the order's products.
- Config: `config/shop.php` → `hold_stock` (`enabled`, `default_minutes`).

## Abandoned Cart Recovery

Three-step email sequence driven by the scheduler.

```
Cart inactive > 1h (has items, no conversion)
        │
        ▼
AbandonedCartService::identifyAbandonedCarts() → markAbandoned() (abandoned_at)
        │
        ▼
shop:abandoned-cart-process (hourly)
   ├─ Step 1 (1h after)  → cart-recovery-1h.blade.php
   ├─ Step 2 (24h after) → cart-recovery-24h.blade.php
   └─ Step 3 (72h after) → cart-recovery-72h.blade.php
        │  (signed URL: /store/cart/recover/{cart})
        ▼
Customer clicks → recover() → cleared abandoned_at + redirected to cart (guest) or portal
```

- Sequence timings come from `abandoned_cart.sequence_hours` (default `[1, 24, 72]`), stored in
  settings as `abandoned_cart_sequence_hours` (CSV `1,24,72`).
- Every cart mutation (`CartController::addItem/updateItem/removeItem/clear/applyCoupon/
  removeCoupon`) calls `touchLastActivity()`.
- On checkout, `converted_to_order_id` is set on the cart (inside the order transaction).

## Coupon Engine

```
Apply code (cart page / checkout)
        │
        ▼
CouponService::validateAndApply(code, subtotal, userId)
   ├─ active? in date range? usage limits? per-user limit? min order?
   └─ throws ValidationException with specific message on failure
        │
        ▼
cart.coupon_code + cart.discount stored
        │
        ▼
CheckoutController::store() → re-validates via CouponService
   └─ CouponService::recordUsage() → CouponUsage row + used_count++
```

Coupon types:

| Type | Behavior |
|------|----------|
| `percentage` | Percent off subtotal |
| `fixed` | Fixed amount off |
| `free_shipping` | Zeroes `shipping_total` (skips min-order check, value field hidden) |
| `is_automatic` flag | Auto-discounts applied without a code, with `auto_discount_label` shown in cart |

`Coupon::calculateDiscount()` accepts an optional `?float $shippingCost` (used by
`free_shipping`). Auto-discounts are surfaced via `CouponService::getAutoDiscounts($subtotal)`
and folded into checkout totals.

## Checkout Flow (guest)

```
/store/checkout (StoreLayout)
   ├─ guest checkout allowed?  (settings.enable_guest_checkout)  else redirect to login
   ├─ require_company_field?   → billing_address.company required
   ├─ require_terms?           → accepted_terms checkbox
   ├─ selling_locations mode?  → billing + shipping country validated
   └─ currency / taxes / shipping applied
        │
        ▼
CheckoutService::checkout()  (DB::transaction)
   ├─ create Order (status pending)
   ├─ apply coupon + auto-discounts
   ├─ TaxService::calculate()  (tax_based_on, inc-tax back-calc, tax_shipping)
   ├─ ShippingService::getAvailableMethods()  (zone match → override prices, else base country)
   └─ InventoryService::recordSaleForOrder()  (stock hold)
        │
        ▼
order.placed notification + order-confirmation email (MailService, guest-safe)
        │
        ▼
confirmation page → optional account creation via signed /store/order/{order}/register
```

## Inventory / Stock

```
Any stock mutation (sale/refund/manual/order)
        │
        ▼
InventoryService::adjust(entity, qty, type, reason, user, reference)
   ├─ Product | ProductVariation support (entity instanceof ProductVariation → variations table)
   ├─ caps at 0
   ├─ writes StockAdjustment (stock_before/after, type, reason, user, variation_id)
   └─ dispatches product.stock_changed webhook
        │
        ▼
Low stock? → low_stock notification to admins + low-stock-alert email
```

Stock status is derived (3-way: `instock` / `outofstock` / `onbackorder`) and recalculated on
save hooks; variations delegate to the parent when using `usesParentStock()`.

## Sales Scheduling

```
Product saved with status=future + publish_date
        │
        ▼
schedule:publish (every minute, core command) → Product::future() where publish_date ≤ now()
        │
        ▼
status → published (sale_price applies when within sale_from/sale_to)
```

`shop:process-scheduled-sales` handles the sale-price window (auto start/stop of
`sale_price`).

## Webhooks

```
Event fired (order.placed, product.updated, stock_changed, …)
        │
        ▼
WebhookService::dispatch(event, payload)
   └─ finds active webhooks subscribed → WebhookSendJob (queued, unique)
        │
        ▼
POST with X-Webhook-Signature (HMAC-SHA256 of body, secret)
        │
        ▼
WebhookLog written (response code/body, duration, success)
   └─ 10 consecutive failures → webhook auto-deactivated
```

Events: `order.placed`, `order.updated`, `order.cancelled`, `order.refunded`,
`order.status_changed`, `product.created`, `product.updated`, `product.deleted`,
`customer.created`, `customer.updated`, `product.stock_changed`, `product.low_stock`,
`shipment.created`, `shipment.shipped`, `shipment.delivered`, `webhook.test`.
`webhooks:process` drains/re-queues the pending queue every minute.

## Notifications

Registered in `getNotificationTypes()`:

| Type | Default channels | Default enabled | Email template |
|------|------------------|-----------------|----------------|
| `order.placed` | in_app, email | yes | order-confirmation |
| `order.status_changed` | in_app, email | yes | order-shipped |
| `low_stock` | in_app | yes | low-stock-alert |
| `product.stock_changed` | in_app | no | — |

Delivery respects per-user preference (`NotificationService::notifyViaPreferences`).

## Related

- [Services](services.md) — the services that implement these flows.
- [Data Model](data-model.md) — the tables behind each state machine.
