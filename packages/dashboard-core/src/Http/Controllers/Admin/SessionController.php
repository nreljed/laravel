<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Models\User;

class SessionController extends Controller
{
    public function index(User $user): Response
    {
        $sessions = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => $session->last_activity,
                'is_current' => $session->id === session()->getId(),
            ]);

        return Inertia::render('dashboard/admin/users/sessions', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'sessions' => $sessions,
        ]);
    }

    public function destroy(User $user, string $sessionId): RedirectResponse
    {
        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        return redirect()->back()
            ->with('flash', ['success' => 'Session revoked successfully.']);
    }
}
