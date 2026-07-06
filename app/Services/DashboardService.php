<?php

namespace App\Services;

use App\Models\User;
use App\Modules\ModuleRegistry;
use App\Modules\Shop\Models\Cart;
use App\Modules\Shop\Models\Order;
use App\Modules\Shop\Models\OrderItem;
use App\Modules\Shop\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function getStats(): array
    {
        return Cache::remember('dashboard.stats', 300, function () {
            $stats = [
                'total_users' => [
                    'label' => 'Total Users',
                    'icon' => 'Users',
                    'value' => User::count(),
                ],
                'new_users_today' => [
                    'label' => 'New Today',
                    'icon' => 'UserPlus',
                    'value' => User::whereDate('created_at', today())->count(),
                ],
            ];

            if (app()->resolved(ModuleRegistry::class)) {
                $registry = app(ModuleRegistry::class);
                $moduleData = $registry->getAllDashboardData();

                foreach ($moduleData as $slug => $data) {
                    $reserved = ['label', 'icon', 'total'];
                    $hasTotal = isset($data['total']);
                    if ($hasTotal) {
                        $stats[$slug] = [
                            'label' => $data['label'] ?? ucfirst($slug),
                            'icon' => $data['icon'] ?? 'FileText',
                            'value' => $data['total'],
                        ];
                    }
                    foreach ($data as $key => $value) {
                        if (in_array($key, $reserved, true)) {
                            continue;
                        }
                        $stats[$slug.'_'.$key] = [
                            'label' => $data['label'].' — '.ucfirst(str_replace('_', ' ', $key)),
                            'icon' => $data['icon'] ?? 'FileText',
                            'value' => $value,
                        ];
                    }
                }
            }

            return $stats;
        });
    }

    public function getAnalytics(): array
    {
        return Cache::remember('dashboard.analytics', 300, function () {
            if (! Schema::hasTable('orders')) {
                return [
                    'kpis' => [],
                    'revenue_over_time' => [],
                    'recent_orders' => [],
                    'top_products' => [],
                    'order_status_counts' => [],
                    'total_revenue' => 0.0,
                    'total_orders' => 0,
                    'total_products' => 0,
                    'total_customers' => 0,
                    'abandoned_carts' => 0,
                    'recovered_carts' => 0,
                ];
            }

            $today = today();
            $yesterday = today()->subDay();
            $thisWeek = [now()->startOfWeek(), now()->endOfWeek()];
            $lastWeek = [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()];
            $thisMonth = [now()->startOfMonth(), now()->endOfMonth()];
            $lastMonth = [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()];

            $completedStatuses = ['completed', 'processing'];

            $revenueToday = Order::whereIn('status', $completedStatuses)->whereDate('created_at', $today)->sum('total');
            $revenueYesterday = Order::whereIn('status', $completedStatuses)->whereDate('created_at', $yesterday)->sum('total');

            $ordersToday = Order::whereDate('created_at', $today)->count();
            $ordersYesterday = Order::whereDate('created_at', $yesterday)->count();

            $aovThisMonth = Order::whereIn('status', $completedStatuses)->whereBetween('created_at', $thisMonth)->avg('total') ?? 0;
            $aovLastMonth = Order::whereIn('status', $completedStatuses)->whereBetween('created_at', $lastMonth)->avg('total') ?? 0;

            $totalCheckouts = Order::whereBetween('created_at', $thisMonth)->count();
            $completedCheckouts = Order::whereIn('status', $completedStatuses)->whereBetween('created_at', $thisMonth)->count();
            $conversionRate = $totalCheckouts > 0 ? round(($completedCheckouts / $totalCheckouts) * 100, 1) : 0;

            $previousTotalCheckouts = Order::whereBetween('created_at', $lastMonth)->count();
            $previousCompletedCheckouts = Order::whereIn('status', $completedStatuses)->whereBetween('created_at', $lastMonth)->count();
            $previousConversionRate = $previousTotalCheckouts > 0 ? round(($previousCompletedCheckouts / $previousTotalCheckouts) * 100, 1) : 0;

            $revenueThisMonth = Order::whereIn('status', $completedStatuses)->whereBetween('created_at', $thisMonth)->sum('total');
            $revenueLastMonth = Order::whereIn('status', $completedStatuses)->whereBetween('created_at', $lastMonth)->sum('total');

            return [
                'kpis' => [
                    [
                        'label' => 'Revenue Today',
                        'value' => (float) $revenueToday,
                        'format' => 'currency',
                        'change' => $this->pctChange($revenueYesterday, $revenueToday),
                        'icon' => 'DollarSign',
                    ],
                    [
                        'label' => 'Orders Today',
                        'value' => $ordersToday,
                        'format' => 'number',
                        'change' => $this->pctChange($ordersYesterday, $ordersToday),
                        'icon' => 'ShoppingCart',
                    ],
                    [
                        'label' => 'Avg Order Value',
                        'value' => (float) $aovThisMonth,
                        'format' => 'currency',
                        'change' => $this->pctChange($aovLastMonth, $aovThisMonth),
                        'icon' => 'TrendingUp',
                    ],
                    [
                        'label' => 'Conversion Rate',
                        'value' => $conversionRate,
                        'format' => 'percent',
                        'change' => $conversionRate - $previousConversionRate,
                        'icon' => 'BarChart3',
                    ],
                    [
                        'label' => 'Revenue This Month',
                        'value' => (float) $revenueThisMonth,
                        'format' => 'currency',
                        'change' => $this->pctChange($revenueLastMonth, $revenueThisMonth),
                        'icon' => 'TrendingUp',
                    ],
                ],
                'revenue_over_time' => $this->getRevenueOverTime(14),
                'recent_orders' => $this->getRecentOrders(5),
                'top_products' => $this->getTopProducts(5),
                'order_status_counts' => Order::select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray(),
                'total_revenue' => (float) Order::whereIn('status', $completedStatuses)->sum('total'),
                'total_orders' => Order::count(),
                'total_products' => Product::count(),
                'total_customers' => Order::whereNotNull('user_id')->distinct()->count('user_id'),
                'abandoned_carts' => Cart::whereNotNull('abandoned_at')->whereNull('recovered_at')->whereNull('converted_to_order_id')->count(),
                'recovered_carts' => Cart::whereNotNull('recovered_at')->count(),
            ];
        });
    }

    public function clearStatsCache(): void
    {
        Cache::forget('dashboard.stats');
        Cache::forget('dashboard.analytics');
    }

    private function getRevenueOverTime(int $days): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $revenue = Order::whereIn('status', ['completed', 'processing'])
                ->whereDate('created_at', $date)
                ->sum('total');
            $count = Order::whereDate('created_at', $date)->count();
            $data[] = [
                'date' => $date->format('M d'),
                'revenue' => (float) $revenue,
                'orders' => $count,
            ];
        }

        return $data;
    }

    private function getRecentOrders(int $limit): array
    {
        return Order::with('items')
            ->latest()
            ->take($limit)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name ?? ($order->user?->name ?? 'Guest'),
                'total' => (float) $order->total,
                'status' => $order->status,
                'items_count' => $order->items->sum('quantity'),
                'created_at' => $order->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    private function getTopProducts(int $limit): array
    {
        return OrderItem::select('product_id', 'product_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total) as total_revenue'))
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_revenue')
            ->take($limit)
            ->get()
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'total_qty' => (int) $item->total_qty,
                'total_revenue' => (float) $item->total_revenue,
            ])
            ->toArray();
    }

    private function pctChange(float|int $previous, float|int $current): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
