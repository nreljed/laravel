<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::with('roles')
            ->when($request->search, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->through(fn ($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'roles' => $user->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug]),
                'created_at' => $user->created_at->toISOString(),
            ]);

        $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('dashboard/admin/users/index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('dashboard/admin/users/form', [
            'roles' => $roles,
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

        if ($request->role_id) {
            $user->roles()->sync([$request->role_id]);
        }

        if ($request->boolean('send_email')) {
            Password::sendResetLink(['email' => $user->email]);
        }

        AuditService::log(AuditAction::RoleAssigned, $request->user()->id, [
            'target_user_id' => $user->id,
            'role_id' => $request->role_id,
        ]);

        $message = $request->boolean('send_email')
            ? 'User created successfully. A password reset link has been sent.'
            : 'User created successfully.';

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => $message]);
    }

    public function edit(User $user): Response
    {
        $user->load('roles');
        $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);

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
            $user->roles()->sync([$request->role_id]);
        }

        AuditService::log(AuditAction::RoleAssigned, $request->user()->id, [
            'target_user_id' => $user->id,
            'role_id' => $request->role_id,
        ]);

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'User updated successfully.']);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('dashboard.admin.users.index')
                ->with('flash', ['error' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'User deleted successfully.']);
    }

    public function verifyEmail(User $user): RedirectResponse
    {
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard.admin.users.index')
                ->with('flash', ['info' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'Email verified for '.$user->name.'.']);
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        $message = match ($status) {
            Password::RESET_LINK_SENT => 'Password reset link sent to '.$user->email.'.',
            default => 'Failed to send password reset link.',
        };

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => $message]);
    }
}
