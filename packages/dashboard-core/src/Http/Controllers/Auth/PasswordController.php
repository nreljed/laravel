<?php

namespace Nreljed\DashboardCore\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Http\Requests\Auth\UpdatePasswordRequest;
use Nreljed\DashboardCore\Services\AuditService;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        AuditService::log(AuditAction::PasswordChange, $user->id);

        return back()->with('status', 'Password updated successfully.');
    }
}
