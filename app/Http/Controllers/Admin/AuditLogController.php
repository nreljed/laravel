<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $query = AuditLog::with('user');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->latest('id')->paginate(50)
            ->through(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'context' => $log->context,
                'created_at' => $log->created_at->toISOString(),
            ]);

        $actions = AuditLog::distinct()->pluck('action')->sort()->values();
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('dashboard/admin/audit-logs/index', [
            'logs' => $logs,
            'actions' => $actions,
            'users' => $users,
            'filters' => $request->only(['action', 'user_id', 'date_from', 'date_to']),
        ]);
    }
}
