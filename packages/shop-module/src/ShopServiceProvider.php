<?php

namespace Nreljed\ShopModule;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Nreljed\Platform\Concerns\InteractsWithFeatures;
use Nreljed\Platform\Contracts\FeatureProvider;
use Nreljed\Platform\Services\SettingsService;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Currency;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductCategory;
use Nreljed\ShopModule\Models\ProductReview;
use Nreljed\ShopModule\Models\ShippingMethod;
use Nreljed\ShopModule\Models\ShippingZone;
use Nreljed\ShopModule\Models\TaxRate;
use Nreljed\ShopModule\Models\Webhook;
use Nreljed\ShopModule\Models\Wishlist;
use Nreljed\ShopModule\Policies\CouponPolicy;
use Nreljed\ShopModule\Policies\CurrencyPolicy;
use Nreljed\ShopModule\Policies\OrderPolicy;
use Nreljed\ShopModule\Policies\ProductPolicy;
use Nreljed\ShopModule\Policies\ProductReviewPolicy;
use Nreljed\ShopModule\Policies\ShippingMethodPolicy;
use Nreljed\ShopModule\Policies\ShippingZonePolicy;
use Nreljed\ShopModule\Policies\TaxRatePolicy;
use Nreljed\ShopModule\Policies\WebhookPolicy;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\ShopService;
use Nreljed\ShopModule\Services\ShopSettingsService;

class ShopServiceProvider extends ServiceProvider implements FeatureProvider
{
    use InteractsWithFeatures;

    public function register(): void
    {
        $this->app->singleton(ShopService::class);

        $this->mergeConfigFrom(__DIR__.'/../config/shop.php', 'shop');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shop');

        if ($this->enabled()) {
            $this->loadRoutes();
        }

        $this->registerPolicies();
        $this->commands([
            Commands\ProcessAbandonedCarts::class,
            Commands\ProcessPendingWebhooks::class,
            Commands\ProcessScheduledSales::class,
            Commands\ReleaseExpiredHolds::class,
            Commands\ShopProductSeed::class,
        ]);
    }

    protected function loadRoutes(): void
    {
        // Customer portal (auth required, no special permission)
        Route::middleware(['web', 'auth', 'verified'])
            ->prefix('/dashboard')->name('dashboard.')
            ->group(__DIR__.'/Routes/customer-portal.php');

        // Admin management (auth + shop.view permission)
        Route::middleware(['web', 'auth', 'verified', 'permission:shop.view'])
            ->prefix('/dashboard/shop')->name('dashboard.shop.')
            ->group(__DIR__.'/Routes/web.php');

        // Admin shop settings (admin + shop.view permission)
        Route::middleware(['web', 'auth', 'verified', 'admin', 'permission:shop.view'])
            ->prefix('/admin/shop')->name('admin.shop.')
            ->group(__DIR__.'/Routes/admin.php');

        // Public storefront / guest routes (no auth)
        Route::middleware(['web'])
            ->group(function () {
                $p = __DIR__.'/Routes/';
                require $p.'store-checkout.php';
                require $p.'recovery.php';
                require $p.'store-cart.php';
                require $p.'store-wishlist.php';
                require $p.'storefront.php';
                require $p.'store-notify.php';
            });

        // Shop JSON API (sanctum / stateful) — route files declare their own api.* names
        Route::middleware(['api'])->prefix('/api')
            ->group(function () {
                $p = __DIR__.'/Routes/';
                require $p.'api.php';
                require $p.'api-auth.php';
            });
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Coupon::class, CouponPolicy::class);
        Gate::policy(Currency::class, CurrencyPolicy::class);
        Gate::policy(ProductReview::class, ProductReviewPolicy::class);
        Gate::policy(ShippingMethod::class, ShippingMethodPolicy::class);
        Gate::policy(ShippingZone::class, ShippingZonePolicy::class);
        Gate::policy(TaxRate::class, TaxRatePolicy::class);
        Gate::policy(Webhook::class, WebhookPolicy::class);
    }

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

    public function getComposerPackage(): ?string
    {
        return 'nreljed/shop-module';
    }

    public function dependencies(): array
    {
        return [];
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
                $items = [
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
                ];

                if ($user->isAdmin()) {
                    $items[] = ['title' => 'Settings', 'url' => route('admin.shop.settings.index', [], false)];
                }

                return [[
                    'title' => 'Shop',
                    'url' => route('dashboard.shop.dashboard', [], false),
                    'icon' => 'ShoppingCart',
                    'permission' => 'shop.view',
                    'module_slug' => 'Shop',
                    'showModeBadge' => false,
                    'items' => $items,
                ]];
            } catch (\Throwable) {
                return [];
            }
        }

        // Customer: show only my account portal (links to existing dashboard pages)
        if ($user->can('shop.view')) {
            try {
                return [[
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
                ]];
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
                'total' => Product::count(),
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
        return [
            'currencies' => function () {
                return app(CurrencyService::class)->getActiveCurrencies()
                    ->map(fn ($currency) => [
                        'code' => $currency->code,
                        'name' => $currency->name,
                        'symbol' => $currency->symbol,
                        'exchange_rate' => $currency->exchange_rate,
                        'decimal_places' => $currency->decimal_places,
                        'is_default' => $currency->is_default,
                    ])
                    ->values()
                    ->all();
            },
            'selectedCurrency' => function () {
                $currencyService = app(CurrencyService::class);
                $code = session('selected_currency');

                if ($code && ($currency = $currencyService->getByCode($code)) && $currency->is_active) {
                    return $currency->code;
                }

                if (! $code) {
                    $active = $currencyService->getActiveCurrencies()->pluck('code')->all();
                    $detected = $currencyService->detectFromAcceptLanguage(request()->header('Accept-Language'), $active);

                    if ($detected && $detected !== ($currencyService->getDefaultCurrency()?->code ?? 'EUR')) {
                        session(['selected_currency' => $detected]);

                        return $detected;
                    }
                }

                return $currencyService->getDefaultCurrency()?->code ?? 'EUR';
            },
            'currencyPosition' => function () {
                try {
                    return SettingsService::get('currency_position', 'before');
                } catch (\Throwable) {
                    return 'before';
                }
            },
            'shopSettings' => function () {
                try {
                    return app(ShopSettingsService::class)->all();
                } catch (\Throwable) {
                    return [];
                }
            },
            'currencyFormat' => function () {
                try {
                    return app(ShopSettingsService::class)->currencyFormat();
                } catch (\Throwable) {
                    return [
                        'position' => 'before',
                        'thousands_separator' => ',',
                        'decimal_separator' => '.',
                        'decimals' => 2,
                    ];
                }
            },
            'cart' => function () {
                $request = request();

                if (! $request->hasSession()) {
                    return ['items' => [], 'subtotal' => 0, 'total' => 0];
                }

                $cart = $request->user()
                    ? Cart::where('user_id', $request->user()->id)->first()
                    : Cart::where('session_id', $request->session()->getId())->first();

                if (! $cart) {
                    return ['items' => [], 'subtotal' => 0, 'total' => 0];
                }

                $cart->load('items.product', 'items.variation');

                return [
                    'id' => $cart->id,
                    'items' => $cart->items->map(fn ($item) => [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'variant_label' => $item->variant_label,
                        'product' => $item->product ? [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'slug' => $item->product->slug,
                            'thumbnail_url' => $item->product->thumbnail_url,
                        ] : null,
                        'variation' => $item->variation ? [
                            'id' => $item->variation->id,
                            'image_url' => $item->variation->image_url,
                        ] : null,
                    ])->values()->all(),
                    'subtotal' => (float) $cart->subtotal,
                    'total' => (float) $cart->total,
                    'coupon_code' => $cart->coupon_code,
                    'discount' => (float) $cart->discount,
                ];
            },
            'wishlistIds' => fn () => request()->user()
                ? Wishlist::where('user_id', request()->user()->id)->pluck('product_id')
                : collect(),
        ];
    }

    public function getSettingsRoute(): ?string
    {
        return 'admin.shop.settings.index';
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
                'hide_in_media_library' => true,
                'create_attachment' => true,
            ],
        ];
    }

    public function getProfileFieldsConfig(): array
    {
        return [];
    }

    public function getProfileSections(): array
    {
        return ['address'];
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
            'cart-recovery-1h' => [
                'label' => 'Cart Recovery (1h)',
                'sample_data' => ['customerName' => 'John Doe', 'cartUrl' => 'https://example.com/cart', 'itemCount' => '3', 'subtotal' => '99.00 EUR'],
                'group' => 'Shop',
            ],
            'cart-recovery-24h' => [
                'label' => 'Cart Recovery (24h)',
                'sample_data' => ['customerName' => 'John Doe', 'cartUrl' => 'https://example.com/cart', 'itemCount' => '3', 'subtotal' => '99.00 EUR'],
                'group' => 'Shop',
            ],
            'cart-recovery-72h' => [
                'label' => 'Cart Recovery (72h)',
                'sample_data' => ['customerName' => 'John Doe', 'cartUrl' => 'https://example.com/cart', 'itemCount' => '3', 'subtotal' => '99.00 EUR'],
                'group' => 'Shop',
            ],
            'order-invoice' => [
                'label' => 'Order Invoice',
                'sample_data' => ['order' => 'N/A', 'siteName' => 'Example Store'],
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
                'default_channels' => ['in_app', 'email'],
                'email_template' => 'low-stock-alert',
                'default_enabled' => true,
                'group' => 'Shop',
            ],
            'product.stock_changed' => [
                'label' => 'Product Stock Changed',
                'description' => 'When a product stock quantity changes.',
                'default_channels' => ['in_app', 'email'],
                'email_template' => 'low-stock-alert',
                'default_enabled' => false,
                'group' => 'Shop',
            ],
        ];
    }

    public function getOrphanMediaTargets(): array
    {
        return [
            'products' => Product::class,
        ];
    }

    public function getMediaSubdirectory(): ?string
    {
        return 'products';
    }

    public function getHealthChecks(): array
    {
        return [
            'products_table' => Schema::hasTable('products'),
            'orders_table' => Schema::hasTable('orders'),
        ];
    }

    public function getDeactivationNotice(): ?string
    {
        return 'The shop module is disabled. Product pages, checkout, and the storefront are unavailable.';
    }

    public function getSettingsGroups(): array
    {
        return ['shop'];
    }

    public function getSitemapUrls(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        $urls = [
            ['loc' => '/store', 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => '/store/products', 'changefreq' => 'daily', 'priority' => '0.8'],
        ];

        $publishedProducts = Product::query()
            ->where('status', 'published')
            ->whereIn('catalog_visibility', ['visible', 'catalog'])
            ->orderBy('updated_at', 'desc')
            ->get(['slug', 'updated_at']);

        foreach ($publishedProducts as $product) {
            $urls[] = [
                'loc' => '/store/'.$product->slug,
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'lastmod' => $product->updated_at?->toDateString(),
            ];
        }

        $categories = ProductCategory::query()
            ->whereHas('products', fn ($q) => $q->where('status', 'published'))
            ->get(['slug']);

        foreach ($categories as $category) {
            $urls[] = [
                'loc' => '/store/category/'.$category->slug,
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        return $urls;
    }

    public function onUserDelete(int $userId): void
    {
        // Orphan orders — keep them, just null the user_id
        Order::where('user_id', $userId)->update(['user_id' => null]);
    }
}
