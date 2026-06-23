<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $displayNameOptions = $this->getDisplayNameOptions($user);

        $phoneData = $user->phone
            ? (is_string($user->phone) ? json_decode($user->phone, true) : $user->phone)
            : [];

        $socialData = $user->social_links
            ? (is_string($user->social_links) ? json_decode($user->social_links, true) : $user->social_links)
            : [];

        return Inertia::render('Profile/Edit', [
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
                'avatar' => $user->avatar_url,
                'custom_avatar_url' => $user->avatar_url,
                'user_registered' => $user->created_at,
                'roles' => $user->roles->pluck('name')->toArray(),
                'module_profile' => [
                    'properties' => [
                        'phone_numbers' => $phoneData,
                        'social_media' => $socialData,
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
            'additional-info' => $this->updatePersonalInfo($request),
            default => redirect()->back()->with('error', 'Invalid update type.'),
        };
    }

    protected function updatePersonalInfo(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'url' => 'nullable|url|max:255',
            'profile_data' => 'nullable|array',
        ]);

        if (isset($validated['description'])) {
            $validated['bio'] = $validated['description'];
            unset($validated['description']);
        }

        if (isset($validated['profile_data'])) {
            $profileData = $validated['profile_data'];
            if (isset($profileData['phone_numbers'])) {
                $user->phone = is_string($profileData['phone_numbers'])
                    ? $profileData['phone_numbers']
                    : json_encode($profileData['phone_numbers']);
            }
            if (isset($profileData['social_media'])) {
                $user->social_links = is_string($profileData['social_media'])
                    ? $profileData['social_media']
                    : json_encode($profileData['social_media']);
            }
            unset($validated['profile_data']);
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        AuditService::log(AuditAction::ProfileUpdate, $user->id, [
            'changed_fields' => array_keys($user->getChanges()),
        ]);

        return Redirect::route('dashboard.profile.edit')->with('status', 'Profile updated successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        AuditService::log(AuditAction::AccountDelete, $user->id);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    protected function getDisplayNameOptions($user): array
    {
        $options = [];

        $options['username'] = $user->name;
        if ($user->nickname) {
            $options['nickname'] = $user->nickname;
        }
        if ($user->first_name) {
            $options['firstname'] = $user->first_name;
        }
        if ($user->last_name) {
            $options['lastname'] = $user->last_name;
        }
        if ($user->first_name && $user->last_name) {
            $options['firstlast'] = $user->first_name.' '.$user->last_name;
            $options['lastfirst'] = $user->last_name.' '.$user->first_name;
        }

        return $options;
    }
}
