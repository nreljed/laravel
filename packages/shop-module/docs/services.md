# Services

The 14 domain services. Constructor injection everywhere (Laravel container);
business logic lives in services, not controllers or models.

| Service | Purpose |
|---------|---------|
| `ShopService` | Singleton; dashboard stats (uses `Order::distinct('user_id')` — the core User model has **no** `orders()` relation) |
| `InventoryService` | Central stock mutations: `adjust()` (Product or ProductVariation), `recordSaleForOrder()`, `recordRefundForOrder()`, manual adjust, history, low-stock + stock-changed webhooks |
| `CheckoutService` | Checkout orchestration: validate stock, record sale, tax, coupon, convert cart, send notifications |
| `CouponService` | `validateAndApply()`, `calculateDiscount()`, `calculateStackedDiscount()`, `recordUsage()`, `getAutoDiscounts()`, `validateStack()` — applies auto-discounts at checkout |
| `OrderStatusService` | 10 statuses + transition map + `canTransition()`/`validateTransition()` |
| `OrderActionService` | Order timeline entries (created/status changed/refunded/edited/notes) |
| `ShippingService` | Zone-matched methods (`whereJsonContains('countries', …)`), fallback to all active |
| `TaxService` | Country-matched rates with `is_default` fallback → `{ tax_total, breakdown }` |
| `WebhookService` | HMAC-signed dispatch, retries, auto-deactivation after failures, webhook calls log |
| `CurrencyService` | Active currencies, default detection, Accept-Language detection |
| `CartMergeService` | Merges guest cart into user cart on login |
| `AbandonedCartService` | Abandonment detection + 3-step email sequence with signed recovery URLs |
| `ShopSettingsService` | Typed DB-backed settings getters over `settings` group `shop` (`baseCountry()`, `guestCheckoutEnabled()`, `currencyFormat()`, `isCountryAllowed()`, …) |
| `StripeService` | Stripe PaymentIntent creation/confirmation/refunds |

## InventoryService

Central stock management — **all stock mutations go through this service.**

**Core methods:**
- `adjust($entity, string $type, int $quantity, ...)` — caps stock at 0, updates
  entity, records a `StockAdjustment`
- `recordSale($entity, int $quantity)` — negative adjustment named "sale"
- `recordRefund($entity, int $quantity)` — positive adjustment named "refund"
- `recordSaleForOrder(Order $order)` — iterates items, calls `recordSale()` per line
  (product or variation)
- `recordRefundForOrder(Order $order)` — same pattern for refunds
- `manualAdjust(Product $product, int $quantity, string $reason)` — manual adjustment
- `getHistory(Product $product, int $perPage = 20)` — paginated adjustment log

**Variation support:** `adjust()` accepts `Product|ProductVariation`.
`StockAdjustment` has an optional `variation_id` FK.

**Integration:** `CheckoutService::checkout()`, `OrdersController::updateStatus()`,
`OrdersController::refund()`, `ReleaseExpiredHolds` command. `recordSaleForOrder()`
runs right after order creation to prevent overselling.

## CouponService

- `validateAndApply(string $code, float $subtotal, ?int $userId)` — validates
  dates/limits/per-user, returns `Coupon` or throws `ValidationException`
- `calculateDiscount(Coupon $coupon, float $subtotal, ?float $shippingCost,
  ?Collection $lineItems)` — delegates to the model (supports all 7 coupon types)
- `calculateStackedDiscount(Collection $coupons, float $subtotal, ?float
  $shippingCost, ?Collection $lineItems)` — applies multiple coupons; only
  percentage/fixed/tiered reduce the running subtotal
- `validateStack(Coupon $newCoupon, array $existingCoupons, float $subtotal)` —
  prevents duplicate / conflicting (e.g. two free_shipping) stack combinations
- `recordUsage(Coupon $coupon, int $orderId, ?int $userId, float $discountAmount)`
  — creates `CouponUsage` + increments `used_count`
- `getAutoDiscounts(float $subtotal)` — active automatic coupons valid for the
  subtotal; auto-discounts are merged into checkout even without a code

## ShippingService

- `getAvailableMethods(?array $billingAddress)` — matches country to zone via
  `whereJsonContains('countries', $country)`, returns zone methods with override
  prices. Falls back to all active methods if no zone match. Base-country fallback
  uses the shop `baseCountry()` setting.

## TaxService

- `calculate(float $subtotal, ?array $billingAddress, bool $isTaxable)` — finds
  rates matching the billing (or shipping, per `tax_based_on`) address country,
  falls back to `is_default`. Returns `{ tax_total, breakdown }`. Handles
  inc-tax back-calculation (`prices_include_tax`) and `tax_shipping`.

## OrderStatusService

10 statuses: `draft`, `pending`, `on-hold`, `confirmed`, `processing`, `completed`,
`cancelled`, `refunded`, `failed`, `trash`. `OrdersController` enforces
`validateTransition()` and records the timeline via `OrderActionService`.

## WebhookService

- `dispatch(string $event, array $payload)` — fans out to all active webhooks
  subscribed to the event; each send runs through the queue with HMAC-SHA256
  signature in `X-Webhook-Signature`, logged to `webhook_calls`, auto-deactivated
  after consecutive failures.
- Webhook **events** are plain strings (not centrally enumerated):
  `order.created`, `order.status_changed`, `order.refunded`,
  `product.stock_changed`, `product.low_stock`, plus `order.placed`,
  `order.updated`, `order.cancelled`, `product.created`, `product.updated`,
  `product.deleted`, `customer.created`, `customer.updated`, `webhook.test` fired
  from controllers.

## AbandonedCartService

- `identifyAbandonedCarts(int $hoursSinceActivity)` — carts with items, no
  conversion, no abandonment mark, activity older than threshold
- `markAbandoned(Cart $cart)` — sets `abandoned_at`
- `sendRecoveryEmail(Cart $cart, int $sequenceStep)` — 1h / 24h / 72h templates
  with signed recovery URLs
- `handleRecovery(Cart $cart)` — marks recovered, redirects (dashboard cart for
  auth users, guest checkout for guests)

## StripeService

Stripe PaymentIntent create/confirm/refund. The listener is
`StripeWebhookController` at `POST /store/webhook/stripe` (signature-verified,
handles `checkout.session.completed` / `charge.refunded`).
