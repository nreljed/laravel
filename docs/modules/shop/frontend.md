# Shop Module — Frontend

## Dashboard Pages

All admin pages in `Resources/js/Pages/`:

| Page | Route | Purpose |
|------|-------|---------|
| `Dashboard.tsx` | `/dashboard/shop/dashboard` | Shop dashboard stats |
| `Products/Index.tsx` | `/dashboard/shop/products` | Product list (DataTable) |
| `Products/Form.tsx` | `/dashboard/shop/products/create`, `/dashboard/shop/products/{product}/edit` | Create/edit product |
| `Products/Show.tsx` | `/dashboard/shop/products/{product}` | Product detail with reviews |
| `Products/StockHistory.tsx` | `/dashboard/shop/products/{product}/stock-history` | Stock adjustment log |
| `Orders/Index.tsx` | `/dashboard/shop/orders` | Order list (DataTable) |
| `Orders/Show.tsx` | `/dashboard/shop/orders/{order}` | Order detail with timeline, refund dialog, notes |
| `Orders/Form.tsx` | `/dashboard/shop/orders/create`, `*/{order}/edit` | Order create/edit |
| `Categories/Index.tsx` | `/dashboard/shop/categories` | Category CRUD |
| `Tags/Index.tsx` | `/dashboard/shop/tags` | Tag CRUD with products count, toggle active |
| `Brands/Index.tsx` | `/dashboard/shop/brands` | Brand CRUD with image/logo, products count, toggle active |
| `Attributes/Index.tsx` | `/dashboard/shop/attributes` | Attribute + term CRUD |
| `Coupons/Index.tsx` | `/dashboard/shop/coupons` | Coupon list |
| `Coupons/Form.tsx` | `/dashboard/shop/coupons/create`, `*/{coupon}/edit` | Coupon create/edit |
| `Reviews/Index.tsx` | `/dashboard/shop/reviews` | Review moderation |
| `Customers/Index.tsx` | `/dashboard/shop/customers` | Customer list |
| `Customers/Show.tsx` | `/dashboard/shop/customers/{user}` | Customer detail + orders |
| `Reports/Index.tsx` | `/dashboard/shop/reports` | Sales reports |
| `ShippingMethods/Index.tsx` | `/dashboard/shop/shipping-methods` | Shipping method CRUD |
| `ShippingZones/Index.tsx` | `/dashboard/shop/shipping-zones` | Shipping zone list |
| `ShippingZones/Form.tsx` | `/dashboard/shop/shipping-zones/create`, `*/{zone}/edit` | Zone create/edit |
| `TaxRates/Index.tsx` | `/dashboard/shop/tax-rates` | Tax rate CRUD |
| `Webhooks/Index.tsx` | `/dashboard/shop/webhooks` | Webhook management |

## Storefront Pages (Guest)

| Page | Route | Purpose |
|------|-------|---------|
| `Storefront/ProductGrid.tsx` | `/store`, `/store/category/{slug}` | Product grid with search/filter/sort |
| `Storefront/ProductDetail.tsx` | `/store/{product}` | Product detail with reviews |
| `Storefront/ShoppingCart.tsx` | `/store/cart` | Guest cart with coupon |
| `Orders/Checkout.tsx` | `/store/checkout` | Multi-step checkout (Details → Shipping → Review) |
| `Orders/Confirmation.tsx` | `/store/checkout/confirmation/{order}` | Post-purchase confirmation |

## Key Components

### `VariationsManager.tsx` (`components/VariationsManager.tsx`)

Inline variation editing component embedded in `Products/Form.tsx`. Merges attribute management + variation table into one cohesive card.

**Attribute Management** (top of card):
- Add global attributes from dropdown (reusable across products)
- Add custom attributes (name + pipe-separated values)
- Toggle visibility (filterable on shop) and variation (used for generation)
- Remove attributes with X button
- **Drag & drop** to reorder attributes (Grabs `sort_order`, persisted via `reorderAttributes` callback)

**Variation Table** (inline editable grid with drag & drop reorder):

| Column | Type | Description |
|--------|------|-------------|
| ⠿ | drag handle | GripVertical icon, drag to reorder (persisted via `reorder` API) |
| ☐ | checkbox | Select for bulk actions |
| ⭐ | button | Set as default variation |
| Attributes | badges | Attribute term names (e.g., "Red / Large") |
| SKU | `Input` | Monospace text input |
| Price | `Input type=number` | Inline decimal input |
| Compare | `Input type=number` | Strikethrough price |
| Stock | `Input type=number` | Editable when `manage_stock=true` |
| Status | badge | Auto-computed: In Stock / Out of Stock / Backorder |
| Weight | `Input type=number` | Inline decimal |
| Backorders | `Select` | No / Notify / Yes (3-way) |
| Img | thumbnail/icon | Opens drawer for image upload |
| On | `Switch` | Toggle active state |
| actions | buttons | Save row ✓, drawer, delete |

**Drawer** (Sheet, right side) — complex fields only:
- Sale Pricing (sale_price, sale_from, sale_to datetime pickers)
- Dimensions (length, width, height)
- Gallery (GalleryUploader with cover image)
- Description (textarea)

**Bulk Operations:**
- "Generate from..." — Select attribute checkboxes → cartesian product generation via backend
- "Bulk Edit" dialog — Set price and/or stock for all rows
- "Save All" button — Sends all dirty rows via `storeBulk()` in one atomic request

**State:**
- `dirty`: row-level patch map (only changed fields sent on save)
- `savingRow` / `saving`: loading states per row or bulk
- Preview mode during product creation shows expected combinations with disabled inputs

## Product Index (`Products/Index.tsx`)

DataTable with columns: Image, Name, SKU, Type, **Categories** (col-span, max 2 badges + `+N` overflow), **Tags** (xl, max 2 badges + `+N` overflow), **Brands** (xl, all badges), Price, Stock, Status, Featured, Actions.

## Product Form (`Products/Form.tsx`)

CRM-dashboard-style layout (2/3 + 1/3 grid):

**Left column (2/3):**
- General: Name, slug, description, short description
- Pricing: Price, compare price, cost price, sale pricing with date range
- Gallery: GalleryUploader with drag-and-drop
- Categories: Searchable multi-select (TermsSelector with inline creation)
- Tags: Token input with suggestions (TermsSelector with inline creation)
- Brands: Searchable multi-select (TermsSelector with inline creation) + free-text vendor input
- Inventory: Stock quantity, track stock toggle, low stock threshold, backorders, SKU, barcode
- Shipping: Weight, dimensions (length, width, height)

**Right sidebar (1/3):**
- Product Type: Simple / Variable selector
- PublishSection: Publish / Draft / Schedule with DateTimePicker
- FeaturedImageSelector: Single featured image with preview
- Featured toggle: Mark as featured product
- VariationsManager: Full attribute + variation editor (variable products only)

## Order Detail (`Orders/Show.tsx`)

- Order items with pricing breakdown
- Customer information with shipping/billing address
- Status timeline with update controls
- Refund button
- Invoice download
- Email notification log

## Cart

### Routes

Cart endpoints are mirrored across admin (auth) and guest (no auth) route groups:

| Method | Admin Route | Guest Route | Response | Purpose |
|--------|------------|-------------|----------|---------|
| GET | `/dashboard/shop/cart` | `/store/cart` | JSON | Cart data |
| GET | `/dashboard/shop/cart/page` | — | Inertia | Cart page |
| POST | `/dashboard/shop/cart/add` | `/store/cart/add` | JSON | Add item |
| PUT | `/dashboard/shop/cart/items/{id}` | `/store/cart/items/{id}` | JSON | Update quantity |
| DELETE | `/dashboard/shop/cart/items/{id}` | `/store/cart/items/{id}` | JSON | Remove item |
| DELETE | `/dashboard/shop/cart` | `/store/cart/clear` | JSON | Clear cart |
| POST | `/dashboard/shop/cart/coupon` | `/store/cart/coupon` | JSON | Apply coupon |
| DELETE | `/dashboard/shop/cart/coupon` | `/store/cart/coupon` | JSON | Remove coupon |

### Frontend Pattern (fetch + reload)

All cart mutation endpoints return JSON, not Inertia responses. Frontend uses `fetch()` for the mutation, then `router.reload({ only: ['cart'] })` to refresh shared props:

```tsx
const updateQuantity = async (itemId: number, newQty: number) => {
  const res = await fetch(route("store.cart.update-item", itemId), {
    method: "PUT",
    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": getCsrfToken() },
    body: JSON.stringify({ quantity: newQty }),
  });
  if (res.ok) router.reload({ only: ["cart"] });
};
```

Do NOT use `router.put()`/`router.delete()` for cart endpoints — they return JSON, causing "All Inertia requests must receive a valid Inertia response" errors.

### Cart Data Shape (shared via `HandleInertiaRequests`)

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

interface CartData {
  id: number;
  items: CartItem[];
  subtotal: number;
  total: number;
  coupon_code: string | null;
  discount: number;
  applied_coupons: Array<{ coupon_id: number; code: string; type: string; discount?: number }> | null;
}
```

### Cart Sidebar Sheet

Rendered inline in `SiteHeader.tsx` using shadcn `Sheet` + `ScrollArea`:
- Product thumbnail image (from `item.product.thumbnail_url` or `item.variation.image_url`)
- Product name as link to detail page
- Variant label (e.g. "Red / Large")
- Quantity +/- buttons with `fetch()`-based live updates and loading state
- Per-item total + remove button
- Subtotal, discount, total in footer
- "View Cart" and "Proceed to Checkout" buttons
