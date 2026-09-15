<?php

namespace Nreljed\ShopModule\Services;

use Illuminate\Support\Facades\DB;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderItem;
use Nreljed\ShopModule\Models\Product;

class ShopService
{
    public function getDashboardStats(): array
    {
        return [
            'total_products' => Product::count(),
            'published_products' => Product::where('status', 'published')->count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'completed_orders' => Order::where('status', 'completed')->count(),
            'low_stock_count' => Product::lowStock()->count(),
            'total_revenue' => Order::whereIn('status', ['completed', 'processing'])->sum('total'),
            'total_customers' => Order::distinct('user_id')->whereNotNull('user_id')->count(),
        ];
    }

    public function getRecentOrders(int $limit = 5): array
    {
        return Order::with('items')
            ->latest()
            ->take($limit)
            ->get()
            ->toArray();
    }

    public function getLowStockProducts(int $limit = 10): array
    {
        return Product::lowStock()
            ->latest()
            ->take($limit)
            ->get()
            ->toArray();
    }

    public function getRevenueByMonth(int $months = 6): array
    {
        $data = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $revenue = Order::whereIn('status', ['completed', 'processing'])
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('total');
            $data[] = [
                'month' => $date->format('M Y'),
                'revenue' => (float) $revenue,
            ];
        }

        return $data;
    }

    public function getTopProducts(int $limit = 5): array
    {
        return OrderItem::select('product_id', 'product_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total) as total_revenue'))
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_revenue')
            ->take($limit)
            ->get()
            ->toArray();
    }
}
