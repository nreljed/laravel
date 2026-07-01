# E-Commerce Module

Manage your online store: products, orders, inventory, and customers.

- **Slug**: `ecommerce`
- **Version**: `1.0.0`
- **Dependencies**: None
- **Has Settings**: Yes
- **Nav Label**: Shop

## Permissions

| Slug | Name |
|------|------|
| `ecommerce.view` | View E-Commerce |
| `ecommerce.products.create` | Create Products |
| `ecommerce.products.edit` | Edit Products |
| `ecommerce.products.delete` | Delete Products |
| `ecommerce.orders.view` | View Orders |
| `ecommerce.orders.edit` | Edit Orders |

## Routes

| File | Prefix | Auth | Middleware |
|------|--------|------|------------|
| `Routes/web.php` | `/dashboard/ecommerce` | Required | `ecommerce.view` |
| `Routes/admin.php` | `/admin/ecommerce` | Admin | `ecommerce.view` |

### Web Routes

Products CRUD + image upload/delete + Orders index/show/update-status + Customers index + Cart API (index, add-item, update-item, remove-item, clear).

### Admin Routes

`PUT /admin/ecommerce/settings` — Update e-commerce settings.

## Nav Items

```
Shop                   (SidebarGroupLabel)
  └─ ECommerce         (collapsible parent)
      ├─ All Products
      ├─ Add Product
      ├─ Orders
      └─ Categories
```

## Dashboard Stats

- `total_products` — All products count
- `published_products` — Published status count
- `total_orders` — All orders count
- `pending_orders` — Pending status count
- `low_stock_count` — Low stock products count

## Gallery Config

- `product_gallery` — max 10 images, upload path `products`, hidden from media library

## Email Templates

| Key | Description |
|-----|-------------|
| `order-confirmation` | Order Confirmation |
| `order-shipped` | Order Shipped |
| `low-stock-alert` | Low Stock Alert |

## Notification Types

| Type | Channels | Template |
|------|----------|----------|
| `order.placed` | in_app + email | `order-confirmation` |
| `order.status_changed` | in_app + email | `order-shipped` |
| `low_stock` | in_app only | — |

## Meta Formatting

- `price_fields`: `['price', 'compare_price', 'cost_price', 'unit_price', 'total', 'subtotal']`

## User Cleanup

On user deletion, order `user_id` is set to null (orders kept for history).

## Policies

| Policy | Model | Scope |
|--------|-------|-------|
| `ProductPolicy` | `Product` | Permission-based (`ecommerce.products.*`) |
| `OrderPolicy` | `Order` | Permission-based (`ecommerce.orders.view/edit`) |

## Migration Paths

Returns `[__DIR__.'/database/migrations']` — the only module with its own migrations (products, orders, cart tables).

## Boot

Registers `ECommerceService` as a singleton in the service container.
