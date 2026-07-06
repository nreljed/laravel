<?php

namespace App\Modules\Shop;

use App\Modules\Contracts\ModuleInterface;
use App\Modules\Shop\Models\Order;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Services\ShopService;
use App\Modules\Traits\HasRoutes;
use Illuminate\Support\Facades\Schema;

class ShopModule implements ModuleInterface
{
    use HasRoutes;

    public function name(): string
    {
        return 'Shop';
    }

    public function slug(): string
    {
        return 'shop';
    }

    public function description(): ?string
    {
        return 'Manage your online store: products, orders, inventory, and customers.';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function routes(): void
    {
        // Customer portal (auth required, no special permission)
        $this->loadRoutes(__DIR__.'/Routes/customer-portal.php', '/dashboard', 'dashboard.', null);

        // Admin management (auth + Shop.view permission)
        $this->loadRoutes(__DIR__.'/Routes/web.php', '/dashboard/shop', 'dashboard.shop.', 'shop.view');
        $this->loadAdminRoutes(__DIR__.'/Routes/admin.php', '/admin/shop', 'admin.shop.', 'shop.view');

        // Public routes (no auth)
        $this->loadPublicRoutes(__DIR__.'/Routes/store-checkout.php', '');
        $this->loadPublicRoutes(__DIR__.'/Routes/recovery.php', '');
        $this->loadApiRoutes(__DIR__.'/Routes/api.php', 'api', 'api.');
        $this->loadApiRoutes(__DIR__.'/Routes/api-auth.php', 'api', 'api.');
        $this->loadPublicRoutes(__DIR__.'/Routes/store-cart.php', '');
        $this->loadPublicRoutes(__DIR__.'/Routes/store-wishlist.php', '');
        $this->loadPublicRoutes(__DIR__.'/Routes/storefront.php', '');
        $this->loadPublicRoutes(__DIR__.'/Routes/store-notify.php', '');
    }

    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        app()->singleton(ShopService::class);
    }

    public function getNavGroupLabel(): ?string
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if ($user->can('shop.products.create')) {
            return 'Manage Shop';
        }

        if ($user->can('shop.view')) {
            return 'My Account';
        }

        return null;
    }

    public function getNavItems(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        // Admin/manager: show only Shop management
        if ($user->can('shop.products.create')) {
            try {
                return [
                    'title' => 'Shop',
                    'url' => route('dashboard.shop.dashboard', [], false),
                    'icon' => 'ShoppingCart',
                    'permission' => 'shop.view',
                    'module_slug' => 'Shop',
                    'showModeBadge' => false,
                    'items' => [
                        ['title' => 'Dashboard', 'url' => route('dashboard.shop.dashboard', [], false)],
                        ['title' => 'All Products', 'url' => route('dashboard.shop.products.index', [], false)],
                        ['title' => 'Add Product', 'url' => route('dashboard.shop.products.create', [], false)],
                        ['title' => 'Orders', 'url' => route('dashboard.shop.orders.index', [], false)],
                        ['title' => 'Create Order', 'url' => route('dashboard.shop.orders.create', [], false)],
                        ['title' => 'Categories', 'url' => route('dashboard.shop.categories.index', [], false)],
                        ['title' => 'Tags', 'url' => route('dashboard.shop.tags.index', [], false)],
                        ['title' => 'Brands', 'url' => route('dashboard.shop.brands.index', [], false)],
                        ['title' => 'Attributes', 'url' => route('dashboard.shop.attributes.index', [], false)],
                        ['title' => 'Customers', 'url' => route('dashboard.shop.customers.index', [], false)],
                        ['title' => 'Reports', 'url' => route('dashboard.shop.reports.index', [], false)],
                        ['title' => 'Coupons', 'url' => route('dashboard.shop.coupons.index', [], false)],
                        ['title' => 'Reviews', 'url' => route('dashboard.shop.reviews.index', [], false)],
                        ['title' => 'Shipping Methods', 'url' => route('dashboard.shop.shipping-methods.index', [], false)],
                        ['title' => 'Shipping Zones', 'url' => route('dashboard.shop.shipping-zones.index', [], false)],
                        ['title' => 'Tax Rates', 'url' => route('dashboard.shop.tax-rates.index', [], false)],
                        ['title' => 'Currencies', 'url' => route('dashboard.shop.currencies.index', [], false)],
                        ['title' => 'Webhooks', 'url' => route('dashboard.shop.webhooks.index', [], false)],
                    ],
                ];
            } catch (\Throwable) {
                return [];
            }
        }

        // Customer: show only my account portal (links to existing dashboard pages)
        if ($user->can('shop.view')) {
            try {
                return [
                    'title' => 'My Account',
                    'url' => route('dashboard.my.orders.index', [], false),
                    'icon' => 'User',
                    'permission' => null,
                    'module_slug' => 'Shop',
                    'showModeBadge' => false,
                    'items' => [
                        ['title' => 'My Orders', 'url' => route('dashboard.my.orders.index', [], false)],
                        ['title' => 'My Cart', 'url' => route('dashboard.my.cart.index', [], false)],
                        ['title' => 'My Account', 'url' => route('dashboard.account', [], false)],
                    ],
                ];
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }

    public function getMetaFormattingConfig(): array
    {
        return [
            'price_fields' => ['price', 'compare_price', 'cost_price', 'unit_price', 'total', 'subtotal'],
            'surface_fields' => [],
        ];
    }

    public function getDashboardData(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        try {
            $service = app(ShopService::class);

            return [
                'label' => 'Shop',
                'icon' => 'ShoppingCart',
                'total_products' => Product::count(),
                'published_products' => Product::where('status', 'published')->count(),
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'pending')->count(),
                'low_stock_count' => Product::lowStock()->count(),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getSharedData(): array
    {
        return [];
    }

    public function getSettingsRoute(): ?string
    {
        return route('dashboard.admin.settings', [], false);
    }

    public function getFrontendPages(): array
    {
        return [
            // Customer portal (dashboard)
            ['path' => '/dashboard/my/orders', 'component' => 'Shop/My/Orders/Index', 'name' => 'dashboard.my.orders.index'],
            ['path' => '/dashboard/my/orders/{order}', 'component' => 'Shop/My/Orders/Show', 'name' => 'dashboard.my.orders.show'],
            ['path' => '/dashboard/my/cart', 'component' => 'Shop/My/Cart/Index', 'name' => 'dashboard.my.cart.index'],

            // Admin management (dashboard)
            ['path' => '/dashboard/shop/dashboard', 'component' => 'Shop/Dashboard', 'name' => 'dashboard.shop.dashboard'],
            ['path' => '/dashboard/shop/products', 'component' => 'Shop/Products/Index', 'name' => 'dashboard.shop.products.index'],
            ['path' => '/dashboard/shop/products/create', 'component' => 'Shop/Products/Form', 'name' => 'dashboard.shop.products.create'],
            ['path' => '/dashboard/shop/products/{product}', 'component' => 'Shop/Products/Show', 'name' => 'dashboard.shop.products.show'],
            ['path' => '/dashboard/shop/products/{product}/edit', 'component' => 'Shop/Products/Form', 'name' => 'dashboard.shop.products.edit'],
            ['path' => '/dashboard/shop/orders', 'component' => 'Shop/Orders/Index', 'name' => 'dashboard.shop.orders.index'],
            ['path' => '/dashboard/shop/orders/create', 'component' => 'Shop/Orders/Form', 'name' => 'dashboard.shop.orders.create'],
            ['path' => '/dashboard/shop/orders/{order}/edit', 'component' => 'Shop/Orders/Form', 'name' => 'dashboard.shop.orders.edit'],
            ['path' => '/dashboard/shop/orders/{order}', 'component' => 'Shop/Orders/Show', 'name' => 'dashboard.shop.orders.show'],
            ['path' => '/dashboard/shop/attributes', 'component' => 'Shop/Attributes/Index', 'name' => 'dashboard.shop.attributes.index'],
            ['path' => '/dashboard/shop/categories', 'component' => 'Shop/Categories/Index', 'name' => 'dashboard.shop.categories.index'],
            ['path' => '/dashboard/shop/tags', 'component' => 'Shop/Tags/Index', 'name' => 'dashboard.shop.tags.index'],
            ['path' => '/dashboard/shop/brands', 'component' => 'Shop/Brands/Index', 'name' => 'dashboard.shop.brands.index'],
            ['path' => '/dashboard/shop/customers', 'component' => 'Shop/Customers/Index', 'name' => 'dashboard.shop.customers.index'],
            ['path' => '/dashboard/shop/customers/{user}', 'component' => 'Shop/Customers/Show', 'name' => 'dashboard.shop.customers.show'],
            ['path' => '/dashboard/shop/reports', 'component' => 'Shop/Reports/Index', 'name' => 'dashboard.shop.reports.index'],
            ['path' => '/dashboard/shop/coupons', 'component' => 'Shop/Coupons/Index', 'name' => 'dashboard.shop.coupons.index'],
            ['path' => '/dashboard/shop/coupons/create', 'component' => 'Shop/Coupons/Form', 'name' => 'dashboard.shop.coupons.create'],
            ['path' => '/dashboard/shop/coupons/{coupon}/edit', 'component' => 'Shop/Coupons/Form', 'name' => 'dashboard.shop.coupons.edit'],
            ['path' => '/dashboard/shop/reviews', 'component' => 'Shop/Reviews/Index', 'name' => 'dashboard.shop.reviews.index'],
            ['path' => '/dashboard/shop/shipping-methods', 'component' => 'Shop/ShippingMethods/Index', 'name' => 'dashboard.shop.shipping-methods.index'],
            ['path' => '/dashboard/shop/shipping-zones', 'component' => 'Shop/ShippingZones/Index', 'name' => 'dashboard.shop.shipping-zones.index'],
            ['path' => '/dashboard/shop/shipping-zones/create', 'component' => 'Shop/ShippingZones/Form', 'name' => 'dashboard.shop.shipping-zones.create'],
            ['path' => '/dashboard/shop/shipping-zones/{shippingZone}/edit', 'component' => 'Shop/ShippingZones/Form', 'name' => 'dashboard.shop.shipping-zones.edit'],
            ['path' => '/dashboard/shop/tax-rates', 'component' => 'Shop/TaxRates/Index', 'name' => 'dashboard.shop.tax-rates.index'],
            ['path' => '/dashboard/shop/currencies', 'component' => 'Shop/Currencies/Index', 'name' => 'dashboard.shop.currencies.index'],
            ['path' => '/dashboard/shop/checkout', 'component' => 'Shop/Orders/Checkout', 'name' => 'dashboard.shop.checkout.index'],
            ['path' => '/dashboard/shop/checkout/confirmation/{order}', 'component' => 'Shop/Orders/Confirmation', 'name' => 'dashboard.shop.checkout.confirmation'],
            ['path' => '/dashboard/shop/products/{product}/stock-history', 'component' => 'Shop/Products/StockHistory', 'name' => 'dashboard.shop.products.stock-history'],
            ['path' => '/dashboard/shop/webhooks', 'component' => 'Shop/Webhooks/Index', 'name' => 'dashboard.shop.webhooks.index'],
            ['path' => '/dashboard/shop/webhooks/{webhook}/calls', 'component' => 'Shop/Webhooks/Calls', 'name' => 'dashboard.shop.webhooks.calls'],

            // Storefront (public)
            ['path' => '/store', 'component' => 'Shop/Storefront/ProductGrid', 'name' => 'store.index'],
            ['path' => '/store/category/{slug}', 'component' => 'Shop/Storefront/ProductGrid', 'name' => 'store.category'],
            ['path' => '/store/{product}', 'component' => 'Shop/Storefront/ProductDetail', 'name' => 'store.show'],
            ['path' => '/store/cart', 'component' => 'Shop/Storefront/ShoppingCart', 'name' => 'store.cart.index'],
            ['path' => '/store/checkout', 'component' => 'Shop/Orders/Checkout', 'name' => 'store.checkout.index'],
            ['path' => '/store/checkout/confirmation/{order}', 'component' => 'Shop/Orders/Confirmation', 'name' => 'store.checkout.confirmation'],
            ['path' => '/store/order/{order}/register', 'component' => 'Shop/Storefront/PostCheckoutRegister', 'name' => 'store.checkout.register-from-order'],
            ['path' => '/store/wishlist', 'component' => 'Shop/Wishlist/Index', 'name' => 'store.wishlist.index'],
        ];
    }

    public function migrationPaths(): array
    {
        return [__DIR__.'/database/migrations'];
    }

    public function permissions(): array
    {
        return [
            ['slug' => 'shop.view', 'name' => 'View Shop', 'description' => 'View Shop section.'],
            ['slug' => 'shop.products.create', 'name' => 'Create Products', 'description' => 'Create new products.'],
            ['slug' => 'shop.products.edit', 'name' => 'Edit Products', 'description' => 'Edit existing products.'],
            ['slug' => 'shop.products.delete', 'name' => 'Delete Products', 'description' => 'Delete products.'],
            ['slug' => 'shop.orders.view', 'name' => 'View Orders', 'description' => 'View order records.'],
            ['slug' => 'shop.orders.edit', 'name' => 'Edit Orders', 'description' => 'Update order status.'],
            ['slug' => 'shop.orders.refund', 'name' => 'Refund Orders', 'description' => 'Issue refunds on orders.'],
            ['slug' => 'shop.tags.manage', 'name' => 'Manage Tags', 'description' => 'Create, edit, and delete product tags.'],
            ['slug' => 'shop.brands.manage', 'name' => 'Manage Brands', 'description' => 'Create, edit, and delete product brands.'],
            ['slug' => 'shop.coupons.manage', 'name' => 'Manage Coupons', 'description' => 'Create, edit, and delete coupons.'],
            ['slug' => 'shop.reviews.moderate', 'name' => 'Moderate Reviews', 'description' => 'Approve, reject, and delete product reviews.'],
            ['slug' => 'shop.shipping.manage', 'name' => 'Manage Shipping', 'description' => 'Manage shipping methods and zones.'],
            ['slug' => 'shop.taxes.manage', 'name' => 'Manage Taxes', 'description' => 'Manage tax rates.'],
            ['slug' => 'shop.webhooks.manage', 'name' => 'Manage Webhooks', 'description' => 'Configure and manage webhook endpoints.'],
            ['slug' => 'shop.currencies.manage', 'name' => 'Manage Currencies', 'description' => 'Manage store currencies.'],
        ];
    }

    public function hasSettings(): bool
    {
        return true;
    }

    public function getRepeaterConfig(): array
    {
        return [];
    }

    public function getGalleryConfig(): array
    {
        return [
            'product_gallery' => [
                'label' => 'Product Gallery',
                'json_key' => 'product_gallery',
                'thumbnail_key' => 'product_thumbnail_url',
                'max_images' => 10,
                'upload_action' => 'dashboard.shop.products.upload-image',
                'delete_action' => 'dashboard.shop.products.delete-image',
                'upload_folder' => 'products',
                'hide_in_media_library' => true,
                'create_attachment' => true,
            ],
        ];
    }

    public function getProfileFieldsConfig(): array
    {
        return [];
    }

    public function getEmailTemplates(): array
    {
        return [
            'order-confirmation' => [
                'label' => 'Order Confirmation',
                'sample_data' => ['customerName' => 'John Doe', 'orderNumber' => 'ORD-ABC123', 'orderTotal' => '$99.00', 'orderStatus' => 'Processing', 'orderUrl' => 'https://example.com/orders/ORD-ABC123'],
                'group' => 'Shop',
            ],
            'order-shipped' => [
                'label' => 'Order Shipped',
                'sample_data' => ['customerName' => 'John Doe', 'orderNumber' => 'ORD-ABC123', 'trackingNumber' => 'TRACK123', 'orderUrl' => 'https://example.com/orders/ORD-ABC123'],
                'group' => 'Shop',
            ],
            'low-stock-alert' => [
                'label' => 'Low Stock Alert',
                'sample_data' => ['productName' => 'T-Shirt', 'productSku' => 'TSH-001', 'stockQuantity' => '3', 'productUrl' => 'https://example.com/products/tshirt-001'],
                'group' => 'Shop',
            ],
        ];
    }

    public function getNotificationTypes(): array
    {
        return [
            'order.placed' => [
                'label' => 'New Order',
                'description' => 'When a new order is placed.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'email_template' => 'order-confirmation',
                'group' => 'Shop',
            ],
            'order.status_changed' => [
                'label' => 'Order Status Changed',
                'description' => 'When an order status changes.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'email_template' => 'order-shipped',
                'group' => 'Shop',
            ],
            'low_stock' => [
                'label' => 'Low Stock Alert',
                'description' => 'When a product stock is low.',
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'group' => 'Shop',
            ],
            'product.stock_changed' => [
                'label' => 'Product Stock Changed',
                'description' => 'When a product stock quantity changes.',
                'default_channels' => ['in_app'],
                'default_enabled' => false,
                'group' => 'Shop',
            ],
        ];
    }

    public function onUserDelete(int $userId): void
    {
        // Orphan orders — keep them, just null the user_id
        Order::where('user_id', $userId)->update(['user_id' => null]);
    }
}
