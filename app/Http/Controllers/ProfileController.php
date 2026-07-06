<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        $displayNameOptions = $user->getDisplayNameOptions();

        $phoneNumbers = $user->phone
            ? (is_string($user->phone) ? json_decode($user->phone, true) : $user->phone)
            : [];

        $socialMedia = $user->social_links
            ? (is_string($user->social_links) ? json_decode($user->social_links, true) : $user->social_links)
            : [];

        return Inertia::render('dashboard/profile/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->name,
                'user_login' => $user->name,
                'display_name' => $user->display_name,
                'display_name_options' => $displayNameOptions,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'nickname' => $user->nickname,
                'bio' => $user->bio,
                'description' => $user->bio,
                'url' => $user->url,
                'email' => $user->email,
                'user_email' => $user->email,
                'contact_email' => $user->contact_email,
                'phone_numbers' => $phoneNumbers,
                'social_media' => $socialMedia,
                'avatar' => $user->avatar_url,
                'custom_avatar_url' => $user->avatar_url,
                'user_registered' => $user->created_at,
                'roles' => $user->roles->pluck('name')->toArray(),
                'module_profile' => [
                    'properties' => [
                        'phone_numbers' => $phoneNumbers,
                        'social_media' => $socialMedia,
                    ],
                ],
            ],
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $updateType = $request->input('_update_type', 'personal-info');

        return match ($updateType) {
            'personal-info' => $this->updatePersonalInfo($request),
            'contact-info' => $this->updateContactInfo($request),
            default => redirect()->back()->with('error', 'Invalid update type.'),
        };
    }

    protected function updatePersonalInfo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'url' => 'nullable|url|max:255',
            'contact_email' => 'nullable|email|max:255',
        ]);

        $this->accountService->updatePersonalInfo($request->user(), $validated);

        return Redirect::route('dashboard.profile.edit')->with('status', 'Profile updated successfully.');
    }

    protected function updateContactInfo(Request $request): RedirectResponse
    {
        $this->accountService->updateContactInfo(
            $request->user(),
            $request->input('phone_numbers'),
            $request->input('social_media'),
        );

        return Redirect::route('dashboard.profile.edit')->with('status', 'Contact info updated successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $this->accountService->deleteAccount($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
