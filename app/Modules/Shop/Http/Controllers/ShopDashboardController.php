<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Product;
use App\Services\DashboardService;
use Inertia\Inertia;

class ShopDashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboardService,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Product::class);

        $analytics = $this->dashboardService->getAnalytics();

        $lowStockCount = Product::where('track_stock', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->count();

        return Inertia::render('Modules/Shop/Dashboard', [
            'analytics' => $analytics,
            'lowStockCount' => $lowStockCount,
        ]);
    }
}
