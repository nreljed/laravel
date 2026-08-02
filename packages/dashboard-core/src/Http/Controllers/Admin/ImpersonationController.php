<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Services\AuditService;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        if ($user->id === $currentUser->id) {
            return redirect()->back()
                ->with('flash', ['error' => 'You cannot impersonate yourself.']);
        }

        if ($user->hasRole('admin') && ! $currentUser->hasRole('admin')) {
            abort(403, 'Cannot impersonate other admins.');
        }

        session()->put('impersonator_id', $currentUser->id);

        AuditService::log(AuditAction::ImpersonationStart, $currentUser->id, [
            'target_user_id' => $user->id,
            'target_name' => $user->name,
            'target_email' => $user->email,
            'target_username' => $user->username,
            'target_display_name' => $user->display_name,
        ]);

        Auth::login($user);

        session()->put('impersonating', true);

        return redirect()->route('dashboard')
            ->with('flash', ['success' => 'You are now acting as '.$user->name.'.']);
    }

    public function stop(Request $request): RedirectResponse
    {
        if (! session()->has('impersonator_id')) {
            return redirect()->route('dashboard')
                ->with('flash', ['error' => 'Not currently impersonating.']);
        }

        $impersonatorId = session()->pull('impersonator_id');
        session()->forget('impersonating');

        $impersonator = User::find($impersonatorId);

        if (! $impersonator) {
            Auth::logout();

            return redirect()->route('login')
                ->with('flash', ['error' => 'Impersonator account not found. Please login again.']);
        }

        $targetUser = $request->user();

        AuditService::log(AuditAction::ImpersonationStop, $impersonatorId, [
            'target_user_id' => $targetUser?->id,
            'target_email' => $targetUser?->email,
        ]);

        Auth::login($impersonator);

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'Returned to your account.']);
    }
}
