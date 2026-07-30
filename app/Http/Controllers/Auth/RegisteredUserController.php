<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (! static::isRegistrationEnabled()) {
            return Inertia::render('Auth/RegisterClosed');
        }

        return Inertia::render('auth/register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        if (! static::isRegistrationEnabled()) {
            return redirect()->route('login')
                ->with('error', 'Registration is currently closed.');
        }

        $user = User::create([
            'username' => $request->username,
            'name' => $request->username,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $defaultRoleSlug = SettingsService::get('default_role');
        $userRole = Role::where('slug', $defaultRoleSlug)
            ->where('slug', '!=', 'admin')
            ->first();

        if (! $userRole) {
            $userRole = Role::where('slug', '!=', 'admin')->orderBy('name')->first();
        }

        if ($userRole) {
            $user->roles()->attach($userRole->id);
        }

        event(new Registered($user));

        Auth::login($user);

        if (SettingsService::get('require_email_verification', '0') === '1') {
            $user->sendEmailVerificationNotification();
        }

        return redirect(route('dashboard', absolute: false));
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $field = $request->input('field');
        $value = $request->input('value');

        if (! in_array($field, ['username', 'email'], true)) {
            return response()->json(['available' => true]);
        }

        $exists = User::where($field, $value)->exists();

        return response()->json(['available' => ! $exists]);
    }

    public static function isRegistrationEnabled(): bool
    {
        try {
            return (bool) SettingsService::get('enable_registration', true);
        } catch (\Throwable) {
            return true;
        }
    }
}
