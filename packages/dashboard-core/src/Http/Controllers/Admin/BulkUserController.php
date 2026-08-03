<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Http\Requests\User\BulkActionRequest;
use Nreljed\DashboardCore\Http\Requests\User\BulkDeleteRequest;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Services\AccountService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkUserController extends Controller
{
    public function delete(BulkDeleteRequest $request): RedirectResponse
    {

        $currentUserId = $request->user()->id;
        $ids = array_filter($request->ids, fn ($id) => (int) $id !== $currentUserId);

        if (empty($ids)) {
            return redirect()->back()
                ->with('flash', ['error' => 'No users to delete (self-deletion excluded).']);
        }

        $users = User::whereIn('id', $ids)->get();
        $count = $users->count();

        foreach ($users as $user) {
            app(AccountService::class)->deleteAllUserData($user);
            $user->delete();
        }

        return redirect()->back()
            ->with('flash', ['success' => "{$count} user(s) deleted successfully."]);
    }

    public function verifyEmail(BulkActionRequest $request): RedirectResponse
    {
        $count = User::whereIn('id', $request->ids)
            ->whereNull('email_verified_at')
            ->get()
            ->each(function ($user) {
                $user->markEmailAsVerified();
                event(new Verified($user));
            })
            ->count();

        return redirect()->back()
            ->with('flash', ['success' => "{$count} user(s) email verified."]);
    }

    public function sendPasswordReset(BulkActionRequest $request): RedirectResponse
    {
        $sent = 0;

        foreach ($request->ids as $id) {
            $user = User::find($id);
            if ($user && Password::sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT) {
                $sent++;
            }
        }

        return redirect()->back()
            ->with('flash', ['success' => "Password reset link sent to {$sent} user(s)."]);
    }

    public function export(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="users-'.now()->format('Y-m-d-His').'.csv"',
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['ID', 'Username', 'Name', 'Email', 'Roles', 'Verified', 'Created At'], escape: '\\');

            User::with('roles')->chunk(100, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->id,
                        $user->username,
                        $user->name,
                        $user->email,
                        $user->roles->pluck('slug')->implode(', '),
                        $user->hasVerifiedEmail() ? 'Yes' : 'No',
                        $user->created_at->toDateTimeString(),
                    ],
                        escape: '\\');
                }
            });

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
