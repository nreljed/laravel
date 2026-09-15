<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;

class ShopDashboardController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Product::class);

        $totalOrders = Order::count();
        $totalRevenue = (float) Order::sum('total');
        $totalProducts = Product::count();
        $totalCustomers = Order::whereNotNull('customer_email')
            ->distinct('customer_email')
            ->count('customer_email');

        $recentOrders = Order::withCount('items')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name ?? ($order->user?->name ?? 'Guest'),
                'total' => (float) $order->total,
                'status' => $order->status,
                'items_count' => $order->items_count,
                'created_at' => $order->created_at?->toISOString(),
            ])
            ->values()
            ->all();

        $lowStockCount = Product::where('track_stock', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->count();

        return Inertia::render('shop/dashboard', [
            'analytics' => [
                'kpis' => [
                    ['label' => 'Revenue', 'value' => $totalRevenue, 'format' => 'currency', 'change' => 0, 'icon' => 'DollarSign'],
                    ['label' => 'Orders', 'value' => $totalOrders, 'format' => 'number', 'change' => 0, 'icon' => 'ShoppingCart'],
                    ['label' => 'Products', 'value' => $totalProducts, 'format' => 'number', 'change' => 0, 'icon' => 'Package'],
                    ['label' => 'Customers', 'value' => $totalCustomers, 'format' => 'number', 'change' => 0, 'icon' => 'Users'],
                ],
                'recent_orders' => $recentOrders,
                'total_revenue' => $totalRevenue,
                'total_orders' => $totalOrders,
                'total_products' => $totalProducts,
                'total_customers' => $totalCustomers,
                'abandoned_carts' => 0,
                'recovered_carts' => 0,
            ],
            'lowStockCount' => $lowStockCount,
        ]);
    }
}
