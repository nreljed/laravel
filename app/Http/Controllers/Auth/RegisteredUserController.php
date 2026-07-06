<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Role;
use App\Models\User;
use App\Modules\Shop\Services\CartMergeService;
use App\Services\SettingsService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (! static::isRegistrationEnabled()) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        if (! static::isRegistrationEnabled()) {
            return redirect()->route('login');
        }

        $user = User::create([
            'username' => $request->username,
            'name' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $defaultRoleSlug = SettingsService::get('default_role', 'crm_user');
        $userRole = Role::where('slug', $defaultRoleSlug)->where('slug', '!=', 'admin')->first();
        if ($userRole) {
            $user->roles()->attach($userRole->id);
        }

        event(new Registered($user));

        app(CartMergeService::class)->mergeGuestCartIntoUserCart($user);

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
