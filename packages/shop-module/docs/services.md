# Services

The 18 domain services. Constructor injection everywhere (Laravel container);
business logic lives in services, not controllers or models.

| Service | Purpose |
|---------|---------|
| `ShopService` | Singleton; dashboard stats (uses `Order::distinct('user_id')` — the core User model has **no** `orders()` relation) |
| `InventoryService` | Central stock mutations: `adjust()` (Product or ProductVariation), `recordSaleForOrder()`, `recordRefundForOrder()`, manual adjust, history, low-stock + stock-changed webhooks |
| `CheckoutService` | Stateless checkout orchestrator: runs the `shop.pipelines.checkout` pipeline inside `DB::transaction`; `processCheckout()` returns the created `Order` |
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

## Pipelines

Cross-cutting flows are composed as Laravel pipelines rather than services. Each
context class is a plain data bag passed through the ordered pipe list from
`config/shop.php` `pipelines` (a pipe returns the context after its step, or hacks
the context to stop the chain).

- `CartContext` — `{ cart, product, variation, quantity, existingItem, variantLabel,
  totalQuantity, unitPrice?, error? }`, run by `CartController::addItem` through
  `pipelines.cart`.
- `CartLineContext` — `{ item, order, currency, attributes }`; fed to each cart
  line by `CreateOrderItems` via `pipelines.cart_lines` to assemble
  `OrderItem::create()` payloads.
- `CheckoutContext` — `{ cart, validated, user, provisionalOrder }` plus public
  `shippingMethod` / `quote` / `order` slots; run by `CheckoutService::processCheckout()`
  through `pipelines.checkout`.

Checkout pipes (in run order): `ValidateStock`, `ResolveShippingMethod`, `BuildQuote`,
`CreateOrder`, `RecordCouponUsage`, `CreateOrderItems`, `RecordInventory`, `ClearCart`.
A pipe returning a non-context value short-circuits the chain.

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

**Integration:** `CheckoutService::processCheckout()`, `OrdersController::updateStatus()`,
`OrdersController::refund()`, `ReleaseExpiredHolds` command. `recordSaleForOrder()`
runs right after order creation (via the `RecordInventory` checkout pipe) to prevent
overselling.

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
  subscribed to the event; each send enqueues a `SendWebhook` job (queued,
  unique per call), HMAC-SHA256 signature in `X-Webhook-Signature`, logged to
  `webhook_calls`, auto-deactivated after 10 consecutive failures.
- `dispatchAndWait(Webhook, string $event, array $payload)` — synchronous,
  single attempt; used by the "send test" button.
- `processPending()` — safety net re-queuing pending deliveries for the
  `webhooks:process` command.
- Webhook **events** are centrally enumerated in `Events/WebhookEvents.php`:
  `order.placed`, `order.updated`, `order.cancelled`, `order.refunded`,
  `order.status_changed`, `product.created`, `product.updated`,
  `product.deleted`, `customer.created`, `customer.updated`,
  `product.stock_changed`, `product.low_stock`, `shipment.created`,
  `shipment.shipped`, `shipment.delivered`, `webhook.test`;
  controller store/update validation restricts subscriptions to this catalog.

## ShipmentService

- `createForOrder(Order $order, ?array $itemQuantities, ?array $options, ?int $createdBy)`
  — creates a `shipment` + `shipment_items` snapshots, generates `SHP-*`
  shipment number, fires `shipment.created`.
- `markShipped(Shipment, ?carrier, ?trackingNumber)` — sets status `shipped` +
  `shipped_at`, fires `shipment.shipped`.
- `markDelivered(Shipment)` — sets status `delivered` + `delivered_at`, fires
  `shipment.delivered`.
- `updateTracking(Shipment, carrier, trackingNumber, ?url)` — fires the matching
  shipped/delivered event if tracking changes on an already-shipped shipment.

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
