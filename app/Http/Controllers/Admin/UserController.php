<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\WelcomeNewUser;
use App\Services\AccountService;
use App\Services\AuditService;
use App\Services\MailService;
use App\Services\SettingsService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('roles')
            ->latest()
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'roles' => $user->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug]),
                'created_at' => $user->created_at->toISOString(),
            ]);

        $roles = Role::orderBy('sort_order')->get(['id', 'name', 'slug']);

        return Inertia::render('dashboard/admin/users/index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $roles = Role::orderBy('sort_order')->get(['id', 'name', 'slug']);

        $defaultRoleSlug = SettingsService::get('default_role', 'editor');
        $defaultRole = Role::where('slug', $defaultRoleSlug)
            ->where('slug', '!=', 'admin')
            ->first() ?? Role::where('slug', 'editor')->first();

        return Inertia::render('dashboard/admin/users/form', [
            'roles' => $roles,
            'defaultRoleId' => $defaultRole?->id,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        $roleId = $request->role_id;
        if (! $roleId) {
            $defaultRoleSlug = SettingsService::get('default_role', 'editor');
            $defaultRole = Role::where('slug', $defaultRoleSlug)
                ->where('slug', '!=', 'admin')
                ->first() ?? Role::where('slug', 'editor')->first();
            $roleId = $defaultRole?->id;
        }

        $roleSlug = $roleId ? Role::find($roleId)?->slug : null;

        AuditService::log(AuditAction::AccountCreated, $request->user()->id, [
            'target_user_id' => $user->id,
            'target_name' => $user->name,
            'target_email' => $user->email,
            'target_username' => $user->username,
            'target_display_name' => $user->display_name,
            'role_id' => $roleId,
            'role_slug' => $roleSlug,
            'notified' => $request->boolean('send_email'),
        ]);

        if ($roleId) {
            $user->roles()->sync([$roleId]);

            AuditService::log(AuditAction::RoleAssigned, $request->user()->id, [
                'target_user_id' => $user->id,
                'role_id' => $roleId,
                'role_slug' => $roleSlug,
            ]);
        }

        if ($request->boolean('send_email')) {
            $token = Password::broker()->createToken($user);
            $user->notify(new WelcomeNewUser($token));
        }

        $message = $request->boolean('send_email')
            ? 'User created successfully. A welcome email has been sent.'
            : 'User created successfully.';

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => $message]);
    }

    public function edit(User $user): Response
    {
        $user->load('roles');
        $roles = Role::orderBy('sort_order')->get(['id', 'name', 'slug']);

        return Inertia::render('dashboard/admin/users/form', [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'role_id' => $user->roles->first()?->id,
            ],
            'roles' => $roles,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => $request->password]);
        }

        if ($request->has('role_id')) {
            $oldRole = $user->roles()->first();
            $user->roles()->sync([$request->role_id]);

            if ($oldRole && (int) $request->role_id !== $oldRole->id) {
                AuditService::log(AuditAction::RoleRevoked, $request->user()->id, [
                    'target_user_id' => $user->id,
                    'role_id' => $oldRole->id,
                    'role_slug' => $oldRole->slug,
                ]);
            }

            $newRole = Role::find($request->role_id);
            AuditService::log(AuditAction::RoleAssigned, $request->user()->id, [
                'target_user_id' => $user->id,
                'role_id' => $request->role_id,
                'role_slug' => $newRole?->slug,
            ]);
        }

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'User updated successfully.']);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('dashboard.admin.users.index')
                ->with('flash', ['error' => 'You cannot delete your own account.']);
        }

        if ($request->boolean('notify_user')) {
            $email = $user->email;
            $displayName = $user->display_name ?: $user->name;
        }

        app(AccountService::class)->deleteAllUserData($user);

        AuditService::log(AuditAction::AccountDelete, $request->user()->id, [
            'target_user_id' => $user->id,
            'target_name' => $user->name,
            'target_email' => $user->email,
            'target_username' => $user->username,
            'target_display_name' => $user->display_name,
            'notified' => $request->boolean('notify_user'),
        ]);

        $user->delete();

        if (isset($email)) {
            try {
                app(MailService::class)->sendNow(
                    $email,
                    '['.config('app.name').'] Your account has been deleted',
                    'account-deleted',
                    [
                        'userName' => $displayName,
                        'siteName' => config('app.name'),
                        'siteUrl' => route('home'),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Deletion notification failed for '.$email.': '.$e->getMessage());
            }
        }

        $message = $request->boolean('notify_user')
            ? 'User deleted successfully. A notification email has been sent.'
            : 'User deleted successfully.';

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => $message]);
    }

    public function verifyEmail(Request $request, User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard.admin.users.index')
                ->with('flash', ['info' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        AuditService::log(AuditAction::EmailVerified, $request->user()->id, [
            'target_user_id' => $user->id,
            'target_email' => $user->email,
        ]);

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'Email verified for '.$user->name.'.']);
    }

    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        $message = match ($status) {
            Password::RESET_LINK_SENT => 'Password reset link sent to '.$user->email.'.',
            default => 'Failed to send password reset link.',
        };

        if ($status === Password::RESET_LINK_SENT) {
            AuditService::log(AuditAction::PasswordResetSent, $request->user()->id, [
                'target_user_id' => $user->id,
                'target_email' => $user->email,
            ]);
        }

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => $message]);
    }
}
