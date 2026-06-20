<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'email_verified_at' => $user->email_verified_at,
                'roles' => $user->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug]),
                'created_at' => $user->created_at->toISOString(),
            ]);

        $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Users/Form', [
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        if ($request->role_id) {
            $user->roles()->sync([$request->role_id]);
        }

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'User created successfully.']);
    }

    public function edit(User $user): Response
    {
        $user->load('roles');
        $roles = Role::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('Admin/Users/Form', [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
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
            $user->update(['password' => bcrypt($request->password)]);
        }

        if ($request->has('role_id')) {
            $user->roles()->sync([$request->role_id]);
        }

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'User updated successfully.']);
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('dashboard.admin.users.index')
            ->with('flash', ['success' => 'User deleted successfully.']);
    }
}
