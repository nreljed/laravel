<?php

namespace Nreljed\DashboardCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Models\Notification;
use Nreljed\DashboardCore\Services\DashboardService;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('dashboard/index');
    }

    public function stats(): Response
    {
        $dashboard = app(DashboardService::class);

        return Inertia::render('dashboard/stats', [
            'stats' => $dashboard->getStats(),
            'analytics' => Inertia::defer(fn () => $dashboard->getAnalytics()),
        ]);
    }

    public function notifications(Request $request): Response
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
                    'account' => ['%user%', '%password%', '%email%', '%account%'],
                    'content' => ['post.%', '%content%'],
                ];

                $patterns = $typePatterns[$typeFilter] ?? ["{$typeFilter}%"];
                foreach ($patterns as $pattern) {
                    $q->orWhere('type', 'like', $pattern);
                }
            });
        }

        $notifications = $query->latest()->paginate(20);
        $unreadCount = Notification::where('notifiable_id', $user->id)
            ->where('notifiable_type', $user::class)
            ->whereNull('read_at')
            ->count();

        return Inertia::render('dashboard/notifications/index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'filter' => $filter,
            'typeFilter' => $typeFilter,
        ]);
    }

    public function switchMode(Request $request): RedirectResponse
    {
        $mode = $request->input('mode', 'host');

        if (! in_array($mode, ['host', 'guest'])) {
            return redirect()->back();
        }

        $request->session()->put('user_mode', $mode);

        return redirect()->back();
    }
}
