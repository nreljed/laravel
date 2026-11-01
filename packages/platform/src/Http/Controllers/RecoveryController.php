<?php

namespace Nreljed\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Nreljed\Platform\Mail\RecoveryMail;
use Nreljed\Platform\Models\RecoveryToken;
use Nreljed\Platform\Models\User;

class RecoveryController extends Controller
{
    public function showRequestForm()
    {
        return view($this->requestView());
    }

    /**
     * The package ships this view under the `core::` namespace; a host may
     * override it by publishing `resources/views/recovery/request.blade.php`.
     *
     * Resolving the bare `recovery.request` name only looked in the host's
     * views, so an un-published install returned 500 "View [recovery.request]
     * not found" on the maintenance recovery page.
     */
    private function requestView(): string
    {
        if (file_exists(resource_path('views/recovery/request.blade.php'))) {
            return 'recovery.request';
        }

        return 'core::recovery.request';
    }

    public function requestToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->input('email'))
            ->whereHas('roles', fn ($q) => $q->where('slug', 'admin'))
            ->first();

        if ($user) {
            $token = RecoveryToken::generate();

            // `send()`, not `queue()`: this mail IS the lockout lifeline. A
            // queued recovery link depends on a worker that shared hosts do not
            // run (the default database queue would silently keep the job
            // forever), which turns "can't reach /login during maintenance" into
            // a permanent lockout. Blocking this one request for an SMTP
            // handshake is the correct trade - and on a log-only mailer the link
            // at least lands in the log instead of vanishing.
            Mail::to($user->email)->send(new RecoveryMail($token));
        }

        return response()->json([
            'message' => 'If an admin account exists with that email, a recovery link has been sent.',
        ]);
    }

    public function claim(string $token)
    {
        $recoveryToken = RecoveryToken::findByToken($token);

        if (! $recoveryToken || ! $recoveryToken->isValid()) {
            return redirect('/login')->withErrors([
                'email' => 'This recovery link is invalid or has expired.',
            ]);
        }

        $recoveryToken->markUsed();

        $cookie = Cookie::make(
            'maintenance_bypass',
            $token,
            1440,
            '/',
            null,
            true,
            false,
            false,
            'lax'
        );

        return redirect('/login')
            ->withCookie($cookie)
            ->with('status', 'Recovery link verified. You may now log in.');
    }

    public static function sendRecoveryEmails(): void
    {
        $token = RecoveryToken::generate();

        $admins = User::whereHas('roles', fn ($q) => $q->where('slug', 'admin'))->get();

        foreach ($admins as $admin) {
            // Synchronous for the same reason as requestToken(): a queued
            // "maintenance is on, here is your way back" mail is useless if no
            // worker ever drains the queue.
            Mail::to($admin->email)->send(new RecoveryMail($token));
        }
    }
}
