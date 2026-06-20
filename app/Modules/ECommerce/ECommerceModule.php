<?php

namespace App\Modules\ECommerce;

use App\Modules\Contracts\ModuleInterface;
use App\Modules\ECommerce\Models\Order;
use App\Modules\ECommerce\Models\Product;
use App\Modules\ECommerce\Services\ECommerceService;
use App\Modules\Traits\HasRoutes;

class ECommerceModule implements ModuleInterface
{
    use HasRoutes;

    public function name(): string
    {
        return 'E-Commerce';
    }

    public function slug(): string
    {
        return 'ecommerce';
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
        $this->loadRoutes(__DIR__ . '/Http/Routes/web.php', '/dashboard/ecommerce', 'dashboard.ecommerce.', 'ecommerce.view');
        $this->loadAdminRoutes(__DIR__ . '/Http/Routes/admin.php', '/admin/ecommerce', 'admin.ecommerce.', 'ecommerce.view');
    }

    public function boot(): void
    {
        //
    }

    public function register(): void
    {
        app()->singleton(ECommerceService::class);
    }

    public function getNavItems(): array
    {
        return [
            'title' => 'ECommerce',
            'url' => route('dashboard.ecommerce.products.index', [], false),
            'icon' => 'ShoppingCart',
            'permission' => 'ecommerce.view',
            'module_slug' => 'ecommerce',
            'showModeBadge' => false,
            'items' => [
                ['title' => 'All Products', 'url' => route('dashboard.ecommerce.products.index', [], false)],
                ['title' => 'Add Product', 'url' => route('dashboard.ecommerce.products.create', [], false)],
                ['title' => 'Orders', 'url' => route('dashboard.ecommerce.orders.index', [], false)],
                ['title' => 'Categories', 'url' => route('dashboard.ecommerce.products.index', [], false) . '?tab=categories'],
            ],
        ];
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
        if (!\Illuminate\Support\Facades\Schema::hasTable('products')) {
            return [];
        }

        try {
            $service = app(ECommerceService::class);
            return [
                'label' => 'E-Commerce',
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
            ['path' => '/dashboard/ecommerce/products', 'component' => 'ECommerce/Products/Index', 'name' => 'dashboard.ecommerce.products.index'],
            ['path' => '/dashboard/ecommerce/products/create', 'component' => 'ECommerce/Products/Form', 'name' => 'dashboard.ecommerce.products.create'],
            ['path' => '/dashboard/ecommerce/products/{product}/edit', 'component' => 'ECommerce/Products/Form', 'name' => 'dashboard.ecommerce.products.edit'],
            ['path' => '/dashboard/ecommerce/orders', 'component' => 'ECommerce/Orders/Index', 'name' => 'dashboard.ecommerce.orders.index'],
            ['path' => '/dashboard/ecommerce/orders/{order}', 'component' => 'ECommerce/Orders/Show', 'name' => 'dashboard.ecommerce.orders.show'],
        ];
    }

    public function migrationPaths(): array
    {
        return [__DIR__ . '/database/migrations'];
    }

    public function permissions(): array
    {
        return [
            ['slug' => 'ecommerce.view', 'name' => 'View E-Commerce', 'description' => 'View e-commerce section.'],
            ['slug' => 'ecommerce.products.create', 'name' => 'Create Products', 'description' => 'Create new products.'],
            ['slug' => 'ecommerce.products.edit', 'name' => 'Edit Products', 'description' => 'Edit existing products.'],
            ['slug' => 'ecommerce.products.delete', 'name' => 'Delete Products', 'description' => 'Delete products.'],
            ['slug' => 'ecommerce.orders.view', 'name' => 'View Orders', 'description' => 'View order records.'],
            ['slug' => 'ecommerce.orders.edit', 'name' => 'Edit Orders', 'description' => 'Update order status.'],
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
                'upload_action' => 'dashboard.ecommerce.products.upload-image',
                'delete_action' => 'dashboard.ecommerce.products.delete-image',
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
                'sample_data' => ['customerName' => 'John Doe', 'orderNumber' => 'ORD-ABC123', 'total' => '$99.00', 'items' => '3'],
                'group' => 'E-Commerce',
            ],
            'order-shipped' => [
                'label' => 'Order Shipped',
                'sample_data' => ['customerName' => 'John Doe', 'orderNumber' => 'ORD-ABC123', 'trackingNumber' => 'TRACK123'],
                'group' => 'E-Commerce',
            ],
            'low-stock-alert' => [
                'label' => 'Low Stock Alert',
                'sample_data' => ['productName' => 'T-Shirt', 'stockQuantity' => '3', 'threshold' => '5'],
                'group' => 'E-Commerce',
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
                'group' => 'E-Commerce',
            ],
            'order.status_changed' => [
                'label' => 'Order Status Changed',
                'description' => 'When an order status changes.',
                'default_channels' => ['in_app', 'email'],
                'default_enabled' => true,
                'email_template' => 'order-shipped',
                'group' => 'E-Commerce',
            ],
            'low_stock' => [
                'label' => 'Low Stock Alert',
                'description' => 'When a product stock is low.',
                'default_channels' => ['in_app'],
                'default_enabled' => true,
                'group' => 'E-Commerce',
            ],
        ];
    }

    public function onUserDelete(int $userId): void
    {
        // Orphan orders — keep them, just null the user_id
        Order::where('user_id', $userId)->update(['user_id' => null]);
    }
}
