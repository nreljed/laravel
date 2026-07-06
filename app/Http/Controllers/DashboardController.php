<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard)
    {
        return Inertia::render('dashboard/index', [
            'stats' => $dashboard->getStats(),
            'analytics' => $dashboard->getAnalytics(),
        ]);
    }

    public function stats()
    {
        return Inertia::render('dashboard/stats');
    }

    public function notifications(Request $request)
    {
        $user = $request->user();

        $filter = $request->input('filter', 'all');
        $typeFilter = $request->input('type_filter', 'all');
        $query = $user->notifications();

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($typeFilter !== 'all') {
            $query->where(function ($q) use ($typeFilter) {
                $typePatterns = [
                    'booking' => ['booking.%', '%booking%'],
                    'property' => ['property.%', '%property%'],
                    'account' => ['%user%', '%password%', '%email%', '%account%'],
                    'content' => ['post.%', '%content%'],
                    'orders' => ['order.%', '%stock%', '%shop%'],
                ];

                $patterns = $typePatterns[$typeFilter] ?? ["{$typeFilter}%"];
                foreach ($patterns as $pattern) {
                    $q->orWhere('type', 'like', $pattern);
                }
            });
        }

        $notifications = $query->latest()->paginate(20);
        $unreadCount = Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->whereNull('read_at')
            ->count();

        return Inertia::render('dashboard/notifications/index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'filter' => $filter,
            'typeFilter' => $typeFilter,
        ]);
    }

    public function switchMode(Request $request)
    {
        if (! $request->user()->hasPermission('bookings.view')) {
            abort(403);
        }

        $mode = $request->input('mode', 'host');

        if (! in_array($mode, ['host', 'guest'])) {
            return redirect()->back();
        }

        $request->session()->put('user_mode', $mode);

        return redirect()->back();
    }
}
