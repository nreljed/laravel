<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Order;
use App\Modules\Shop\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);
        $range = $request->input('range', '30');

        $startDate = match ($range) {
            '7' => Carbon::now()->subDays(7),
            '90' => Carbon::now()->subDays(90),
            '365' => Carbon::now()->subYear(),
            default => Carbon::now()->subDays(30),
        };

        $completedOrders = Order::where('status', 'completed')
            ->where('created_at', '>=', $startDate);

        $summary = [
            'total_revenue' => (float) (clone $completedOrders)->sum('total'),
            'total_orders' => (clone $completedOrders)->count(),
            'avg_order_value' => 0,
            'total_products_sold' => (int) OrderItem::whereIn('order_id', (clone $completedOrders)->pluck('id'))->sum('quantity'),
        ];
        $summary['avg_order_value'] = $summary['total_orders'] > 0
            ? round($summary['total_revenue'] / $summary['total_orders'], 2)
            : 0;

        $salesOverTime = Order::where('status', 'completed')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'orders' => (int) $row->orders,
                'revenue' => (float) $row->revenue,
            ]);

        $topProducts = OrderItem::selectRaw('product_id, product_name, SUM(quantity) as total_qty, SUM(total) as total_revenue')
            ->whereIn('order_id', (clone $completedOrders)->pluck('id'))
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get()
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'total_qty' => (int) $item->total_qty,
                'total_revenue' => (float) $item->total_revenue,
            ]);

        return Inertia::render('Modules/Shop/Reports/Index', [
            'summary' => $summary,
            'salesOverTime' => $salesOverTime,
            'topProducts' => $topProducts,
            'range' => $range,
        ]);
    }
}
