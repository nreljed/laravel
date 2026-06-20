<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:60',
                'unique:'.User::class,
                'regex:/^[a-zA-Z0-9_.-]+$/',
            ],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'username' => $request->username,
            'name' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $userRole = Role::where('slug', 'crm_user')->first();
        if ($userRole) {
            $user->roles()->attach($userRole->id);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $field = $request->input('field');
        $value = $request->input('value');

        if (!in_array($field, ['username', 'email'], true)) {
            return response()->json(['available' => true]);
        }

        $exists = User::where($field, $value)->exists();

        return response()->json(['available' => !$exists]);
    }
}
