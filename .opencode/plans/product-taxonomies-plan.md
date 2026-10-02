# Product Taxonomies Implementation Plan

## Overview
Enable inline creation + full admin management for all product taxonomies (categories, tags, brands) across Shop and Posts modules. Follow the `TermsSelector` + `onCreateTerm` pattern already established.

---

## Phase A: Fix Existing Taxonomies

### A1: Fix Categories inline creation
- **File**: `app/Modules/Shop/Http/Controllers/CategoriesController.php`
- **Change**: `store()` returns `response()->json(['id' => $category->id, 'name' => $category->name])` when `$request->wantsJson()`
- **Why**: `handleCreateCategory` in Form.tsx uses `fetch()` + `res.json()` but controller returns redirect � category is created but never auto-selected

### A2: Create TagsController
- **File**: `app/Modules/Shop/Http/Controllers/TagsController.php`
- Full CRUD: index, store, update, destroy
- `store()`/`update()` return JSON when `$request->wantsJson()`, redirect otherwise
- Auth: `$this->authorize('create', Product::class)` (uses existing permission)
- Validation: name (required|max:255|unique:product_tags,name), description (nullable), sort_order (nullable|integer|min:0), is_active (boolean)

### A3: Create Tags admin page
- **File**: `app/Modules/Shop/Resources/js/Pages/Tags/Index.tsx`
- Pattern: Mirrors `Categories/Index.tsx`
- Table columns: Name, Slug, Products count, Status (Switch), Actions (Edit/Delete)
- Create dialog: name input
- Edit dialog: name, description, sort_order, is_active (Switch)
- Delete: AlertDialog confirmation

### A4: Register Tags in ShopModule
- **File**: `app/Modules/Shop/ShopModule.php`
- Add nav item: `['title' => 'Tags', 'url' => route('dashboard.shop.tags.index', [], false)]` (after Categories)
- Add frontend page: `['path' => '/dashboard/shop/tags', 'component' => 'Shop/Tags/Index', 'name' => 'dashboard.shop.tags.index']`
- Add permission: `['slug' => 'shop.tags.manage', 'name' => 'Manage Tags', 'description' => 'Create, edit, and delete product tags.']`

### A5: Enable Tags inline creation in Form.tsx
- **File**: `app/Modules/Shop/Resources/js/Pages/Products/Form.tsx`
- Change `<TermsSelector canAdd={false} ...>` ? `<TermsSelector canAdd={true} ...>`
- Replace `handleCreateTag` error-toast with real fetch to `route("dashboard.shop.tags.store")`

### A6: Fix Post tag creation
- **File**: `app/Modules/Posts/Resources/js/Pages/Form.tsx`
- `addTag()` currently only matches existing tags by name
- Add POST to `route("dashboard.posts.tags.store")` when tag not found, then add returned ID to `tag_ids`

### A7: Add Posts Tags nav item
- **File**: `app/Modules/Posts/PostsModule.php`
- Add nav item for tag management (or keep JSON-only and add inline modal)
- Alternatively, add a proper admin page like Categories

---

## Phase B: Add Brands Taxonomy

### B1: Migration
- **File**: `app/Modules/Shop/database/migrations/2026_07_05_000000_create_product_brands_table.php`
```php
Schema::create('product_brands', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('image')->nullable();
    $table->integer('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('product_product_brand', function (Blueprint $table) {
    $table->foreignId('product_brand_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained((new Product)->getTable())->cascadeOnDelete();
    $table->primary(['product_brand_id', 'product_id']);
});
```

### B2: ProductBrand Model
- **File**: `app/Modules/Shop/Models/ProductBrand.php`
- Same pattern as `ProductTag`:
  - Fillable: name, slug, description, image, sort_order, is_active
  - Casts: is_active (boolean), sort_order (integer)
  - Boot: auto-slug on creating
  - Relations: products() BelongsToMany via `product_product_brand`
  - Scope: active()

### B3: Add relation to Product model
- **File**: `app/Modules/Shop/Models/Product.php`
- Add: `brands()` BelongsToMany via `product_product_brand`

### B4: BrandsController
- **File**: `app/Modules/Shop/Http/Controllers/BrandsController.php`
- Same pattern as TagsController (full CRUD, JSON for AJAX)
- Auth: `$this->authorize('create', Product::class)` + `$this->authorize('viewAny', Product::class)`

### B5: Brands admin page
- **File**: `app/Modules/Shop/Resources/js/Pages/Brands/Index.tsx`
- Same pattern as Tags/Index.tsx
- Additional: brand image logo URL input + preview

### B6: Brand routes
- **File**: `app/Modules/Shop/Routes/web.php`
```
GET    /brands           -> index
POST   /brands           -> store
PUT    /brands/{brand}   -> update
DELETE /brands/{brand}   -> destroy
```

### B7: Register in ShopModule
- Nav item: `['title' => 'Brands', ...]` (after Tags)
- Frontend page: `Shop/Brands/Index`
- Permission: `shop.brands.manage`

### B8: Vendor field + Brand in Form.tsx
- Migration: Add `vendor` string column to products table
- Product model: add `vendor` to fillable
- Form.tsx: Add Vendor text input + Brand TermsSelector in the form layout

### B9: Add TermsSelector for Brands in Form.tsx
- Between Categories and Tags sections
- `<TermsSelector canAdd={true} onCreateTerm={handleCreateBrand} ...>`
- `handleCreateBrand` ? fetch POST to `dashboard.shop.brands.store`

---

## Updated Navigation Order (Shop sidebar)

```
Dashboard
All Products
Add Product
Orders
Create Order
Categories       ? existing, fix JSON
Tags             ? NEW
Brands           ? NEW
Attributes
Customers
Reports
Coupons
Reviews
...
```

## Shop Permissions (updated)

Add two new permissions:
- `shop.tags.manage` � Manage product tags
- `shop.brands.manage` � Manage product brands

## Migration Strategy

Run in order:
1. `php artisan migrate` (brands table + pivot + vendor column)
2. `php artisan modules:sync-permissions` (new permissions)
3. `npm run build` (frontend)

## Verification

- `npm run typecheck` � no TS errors
- `php artisan test` � all tests pass
- Manual: Create product, create category inline, create tag inline, create brand inline
