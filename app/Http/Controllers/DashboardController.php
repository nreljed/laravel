<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(DashboardService $dashboard)
    {
        return Inertia::render('Dashboard/Index', [
            'stats' => $dashboard->getStats(),
        ]);
    }

    public function stats()
    {
        return Inertia::render('Dashboard/Stats');
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
            $query->where('type', 'like', "%{$typeFilter}%");
        }

        $notifications = $query->latest()->paginate(20);

        return Inertia::render('Dashboard/Notifications', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'filter' => $filter,
            'typeFilter' => $typeFilter,
        ]);
    }

    public function markRead(Request $request)
    {
        $request->validate(['id' => 'required|string']);

        $notification = $request->user()->notifications()->findOrFail($request->id);
        $notification->markAsRead();

        return redirect()->back();
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return redirect()->back();
    }

    public function destroyNotification(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return redirect()->back();
    }

    public function markBulk(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return redirect()->back();
        }

        $request->user()->notifications()->whereIn('id', $ids)->update(['read_at' => now()]);

        return redirect()->back();
    }

    public function deleteBulk(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return redirect()->back();
        }

        $request->user()->notifications()->whereIn('id', $ids)->delete();

        return redirect()->back();
    }

    public function switchMode(Request $request)
    {
        $mode = $request->input('mode', 'host');

        if (!in_array($mode, ['host', 'guest'])) {
            return redirect()->back();
        }

        $request->session()->put('user_mode', $mode);

        return redirect()->back();
    }
}
