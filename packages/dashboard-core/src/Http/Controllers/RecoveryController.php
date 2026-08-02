<?php

namespace Nreljed\DashboardCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Mail;
use Nreljed\DashboardCore\Mail\RecoveryMail;
use Nreljed\DashboardCore\Models\RecoveryToken;
use Nreljed\DashboardCore\Models\User;

class RecoveryController extends Controller
{
    public function showRequestForm()
    {
        return view('recovery.request');
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
            Mail::to($user->email)->queue(new RecoveryMail($token));
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
            Mail::to($admin->email)->queue(new RecoveryMail($token));
        }
    }
}
