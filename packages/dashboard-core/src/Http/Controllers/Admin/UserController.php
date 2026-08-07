<?php

namespace Nreljed\DashboardCore\Http\Controllers\Admin;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Http\Requests\Admin\StoreUserRequest;
use Nreljed\DashboardCore\Http\Requests\Admin\UpdateUserRequest;
use Nreljed\DashboardCore\Models\Role;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Notifications\WelcomeNewUser;
use Nreljed\DashboardCore\Services\AccountService;
use Nreljed\DashboardCore\Services\AuditService;
use Nreljed\DashboardCore\Services\MailService;
use Nreljed\DashboardCore\Services\SettingsService;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('roles')
            ->latest()
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'avatar_url' => $user->avatar_url,
                'roles' => $user->roles->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug]),
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

        $defaultRoleSlug = SettingsService::get('default_role', config('roles.default_role', 'editor'));
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

        $roleIds = $this->resolveRoleIds($request);
        $roleSlugs = Role::whereIn('id', $roleIds)->pluck('slug')->all();

        AuditService::log(AuditAction::AccountCreated, $request->user()->id, [
            'target_user_id' => $user->id,
            'target_name' => $user->name,
            'target_email' => $user->email,
            'target_username' => $user->username,
            'target_display_name' => $user->display_name,
            'role_ids' => $roleIds,
            'role_slugs' => $roleSlugs,
            'notified' => $request->boolean('send_email'),
        ]);

        if ($roleIds !== []) {
            $user->roles()->sync($roleIds);
            $user->invalidatePermissionCache();

            AuditService::log(AuditAction::RoleAssigned, $request->user()->id, [
                'target_user_id' => $user->id,
                'role_ids' => $roleIds,
                'role_slugs' => $roleSlugs,
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
                'role_ids' => $user->roles->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
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

        if ($request->user()->can('assign-roles') && $request->has('role_ids')) {
            $oldIds = $user->roles()->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            $newIds = array_values(array_unique(array_map('intval', $request->input('role_ids', []))));

            $addedIds = array_values(array_diff($newIds, $oldIds));
            $removedIds = array_values(array_diff($oldIds, $newIds));

            if ($addedIds !== [] || $removedIds !== []) {
                $user->roles()->sync($newIds);
                $user->invalidatePermissionCache();

                if ($addedIds !== []) {
                    AuditService::log(AuditAction::RoleAssigned, $request->user()->id, [
                        'target_user_id' => $user->id,
                        'role_ids' => $addedIds,
                        'role_slugs' => Role::whereIn('id', $addedIds)->pluck('slug')->all(),
                    ]);
                }

                if ($removedIds !== []) {
                    AuditService::log(AuditAction::RoleRevoked, $request->user()->id, [
                        'target_user_id' => $user->id,
                        'role_ids' => $removedIds,
                        'role_slugs' => Role::whereIn('id', $removedIds)->pluck('slug')->all(),
                    ]);
                }
            }
        }

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'User updated successfully.']);
    }

    /**
     * Resolve the role ids to assign on creation. Users with the
     * "assign-roles" permission may set roles explicitly; otherwise the
     * default registration role is applied.
     *
     * @return list<int>
     */
    protected function resolveRoleIds(Request $request): array
    {
        if ($request->user()->can('assign-roles') && $request->filled('role_ids')) {
            return array_values(array_unique(array_map('intval', $request->input('role_ids', []))));
        }

        $defaultRoleSlug = SettingsService::get('default_role', config('roles.default_role', 'editor'));
        $defaultRole = Role::where('slug', $defaultRoleSlug)
            ->where('slug', '!=', 'admin')
            ->first() ?? Role::where('slug', 'editor')->first();

        return $defaultRole ? [$defaultRole->id] : [];
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
        $user->invalidatePermissionCache();

        if (isset($email, $displayName)) {
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
