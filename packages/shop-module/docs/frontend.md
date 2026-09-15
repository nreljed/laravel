# Frontend

Shop pages live inside the package under `src/Resources/js/` and are registered into the host
bundle through **Vite glob imports** (`import.meta.glob('@shop/Pages/**/*.tsx')` in the host
`app.tsx`, with `projectPrefix: 'shop'`). The `@shop` alias maps to
`vendor/nreljed/shop-module/src/Resources/js/`.

## Directory Layout

```
src/Resources/js/
├── Pages/                 # Inertia page components (glob-registered)
│   ├── Dashboard.tsx
│   ├── Products/{Index,Form,Show,StockHistory}.tsx
│   ├── Orders/{Index,Form,Show,Checkout,Confirmation}.tsx
│   ├── Cart/Index.tsx     # admin cart
│   ├── Customers/{Index,Show}.tsx
│   ├── Categories/Index.tsx
│   ├── Tags/Index.tsx
│   ├── Brands/Index.tsx
│   ├── Attributes/Index.tsx
│   ├── Coupons/{Index,Form}.tsx
│   ├── Reviews/Index.tsx
│   ├── ShippingMethods/Index.tsx
│   ├── ShippingZones/{Index,Form}.tsx
│   ├── TaxRates/Index.tsx
│   ├── Currencies/Index.tsx
│   ├── Webhooks/{Index,Calls}.tsx
│   ├── Reports/Index.tsx
│   ├── Settings/Index.tsx
│   ├── Wishlist/Index.tsx
│   ├── My/{Orders,Cart}/…  # customer portal
│   └── Storefront/{Home,ProductGrid,ProductDetail,ShoppingCart,PostCheckoutRegister}.tsx
├── components/
│   ├── AttributeSelector.tsx     # 4-mode swatch rendering (text/color/image/button)
│   ├── CurrencySwitcher.tsx
│   ├── ProductSeoPanel.tsx
│   ├── StarRating.tsx
│   ├── StoreHeader.tsx
│   ├── VariationsManager.tsx
│   ├── WishlistButton.tsx
│   ├── storefront/ProductCard.tsx
│   └── templates/{card-grid,filter-bar,page-header,pagination-links,table-list}.tsx
├── Layouts/StoreLayout.tsx       # guest storefront layout (header, cart badge, mobile menu)
├── lib/currency.ts               # currency-format-aware price formatting
└── overrides/account/profile.tsx
```

## Page Registration

- Pages are consumed with the `shop/*` prefix — e.g. `Pages/Products/Form.tsx` renders as
  page key `shop/products/form` (`Inertia::render` in the controller uses the same key).
- `getFrontendPages()` (from `InteractsWithFeatures`) returns the list the host uses for
  nav/permission wiring.

## Key Patterns

### Cart fetch + reload pattern

Cart mutation endpoints (`CartController::updateItem()`, `removeItem()`, …) return **JSON**,
not Inertia responses. The frontend must therefore use `fetch()` and then refresh the shared
cart prop:

```ts
const res = await fetch(route('store.cart.update-item', itemId), {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
  body: JSON.stringify({ quantity: newQty }),
});
if (res.ok) router.reload({ only: ['cart'] });
```

> Using `router.put()`/`router.delete()` against these endpoints raises
> "All Inertia requests must receive a valid Inertia response" — the endpoint responds with
> JSON, not an Inertia page.

### Shared cart prop shape

`getSharedData()['cart']` provides a normalized cart for the header badge / sidebar:

```ts
interface CartItem {
  id: number;
  product_id: number;
  quantity: number;
  unit_price: number;
  variant_label: string | null;
  product: { id: number; name: string; slug: string; thumbnail_url: string | null } | null;
  variation: { id: number; image_url: string | null } | null;
}
```

### Attribute selector (swatches)

`AttributeSelector.tsx` renders a variation's attribute picker data-driven by the parent
attribute's `display_type`:

| `display_type` | Rendering |
|----------------|-----------|
| `text` | Badge chips |
| `color` | 40×40 circular swatches with Check icon (hex via `swatch_value`, fallback `COLOR_NAMES`) |
| `image` | Circular swatches with `backgroundImage` |
| `button` | Rectangle buttons with term text |

Term `swatch_value` is interpreted based on the parent attribute's `display_type` (hex for
`color`, URL for `image`).

### Star rating

`StarRating.tsx` — reusable: `value`, optional `onChange`, `size` (`sm`/`md`/`lg`), `readonly`,
`maxStars`. Filled stars `fill-amber-400 text-amber-400`; empty `fill-none text-muted-foreground/30`.

### Templates

`components/templates/` holds small layout primitives shared across admin pages
(`card-grid`, `filter-bar`, `page-header`, `pagination-links`, `table-list`) — the same
pattern used by the core dashboard's list pages.

## Currency Formatting

`lib/currency.ts` consumes the `currencyFormat` shared prop
(`{ position, thousands_separator, decimal_separator, decimals }`) so every price renders with
the store's configured separators and symbol position:

```ts
import { formatPrice } from '../lib/currency';
formatPrice(product.active_price); // "1 234,56 €" depending on config
```

## Storefront Layout

`Layouts/StoreLayout.tsx` is the guest storefront shell — sticky header with cart badge (item
count), mobile hamburger menu, sign-in link, and the shared cart sidebar with quantity
controls (same fetch+reload pattern).

## Related

- [Architecture](architecture.md) — how the host wires `@shop`, page globs, and Tailwind.
- [Controllers](controllers.md) — the Inertia responses behind each page.
- [Settings](settings.md) — `Settings/Index.tsx` and the `shopSettings`/`currencyFormat` props.
