<?php

namespace Nreljed\Platform\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Nreljed\Platform\Enums\AuditAction;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Http\Requests\Auth\UpdatePasswordRequest;
use Nreljed\Platform\Services\AuditService;

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
