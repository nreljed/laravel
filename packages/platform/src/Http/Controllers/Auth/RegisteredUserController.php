<?php

namespace Nreljed\Platform\Http\Controllers\Auth;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Http\Requests\Auth\RegisterRequest;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Notifications\AppNotification;
use Nreljed\Platform\Notifications\WelcomeNewUser;
use Nreljed\Platform\Services\NotificationService;
use Nreljed\Platform\Services\SettingsService;

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

        $defaultRoleSlug = SettingsService::get('default_role', config('roles.default_role', 'editor'));
        $userRole = Role::where('slug', $defaultRoleSlug)
            ->where('slug', '!=', 'admin')
            ->first();

        if (! $userRole) {
            $userRole = Role::where('slug', 'editor')->first();
        }

        if ($userRole) {
            $user->roles()->attach($userRole->id);
        }

        event(new Registered($user));

        Auth::login($user);

        if (SettingsService::get('require_email_verification', '0') === '1') {
            $user->sendEmailVerificationNotification();
        }

        $this->dispatchRegistrationNotifications($user);

        return redirect(route('dashboard.index', absolute: false));
    }

    /**
     * Emit the `user.welcome` and `user.registered` notification types for a
     * freshly created account.
     *
     * `user.welcome` greets the new user on the registry's email channel (the
     * `welcome` template). `user.registered` is a required type, so every admin
     * receives it in-app and by email (the `new-user-admin` template) whether or
     * not they have muted account notifications.
     *
     * Failures are logged and swallowed: a notification problem must never abort
     * registration.
     */
    protected function dispatchRegistrationNotifications(User $user): void
    {
        try {
            $notifications = app(NotificationService::class);

            $notifications->notifyViaPreferences(
                $user,
                'user.welcome',
                new WelcomeNewUser,
            );

            $displayName = $user->display_name ?: $user->name;
            $profileUrl = route('dashboard.admin.users.edit', $user);

            User::query()
                ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))
                ->get()
                ->each(function (User $admin) use ($notifications, $user, $displayName, $profileUrl) {
                    $notifications->notifyViaPreferences(
                        $admin,
                        'user.registered',
                        new AppNotification('user.registered', [
                            'title' => 'New user registration: '.$displayName,
                            'message' => 'A new account has been created for '.$user->email.'.',
                            'link' => $profileUrl,
                            'severity' => 'info',
                            'module' => 'core',
                            'userName' => $displayName,
                            'userLogin' => $user->username,
                            'userEmail' => $user->email,
                            'profileUrl' => $profileUrl,
                        ]),
                    );
                });
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch registration notifications for: '.$user->email.' | Error: '.$e->getMessage());
        }
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $field = $request->input('field');
        $value = $request->input('value');

        if (! in_array($field, ['username', 'email'], true)) {
            return response()->json(['available' => true]);
        }

        if ($field === 'username') {
            $value = Str::lower(trim((string) $value));
        }

        $exists = $field === 'username'
            ? User::whereRaw('LOWER(username) = ?', [$value])->exists()
            : User::where('email', $value)->exists();

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
