<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Services\AccountService;
use App\Services\AuditService;
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

        $displayNameOptions = $this->getDisplayNameOptions($user);

        $phoneNumbers = $user->phone
            ? (is_string($user->phone) ? json_decode($user->phone, true) : $user->phone)
            : [];

        $socialMedia = $user->social_links
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
        $user = $request->user();

        $validated = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nickname' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'url' => 'nullable|url|max:255',
            'contact_email' => 'nullable|email|max:255',
        ]);

        if (isset($validated['description'])) {
            $validated['bio'] = $validated['description'];
            unset($validated['description']);
        }

        $user->fill($validated);
        $user->save();

        AuditService::log(AuditAction::ProfileUpdate, $user->id, [
            'changed_fields' => array_keys($user->getChanges()),
        ]);

        return Redirect::route('dashboard.profile.edit')->with('status', 'Profile updated successfully.');
    }

    protected function updateContactInfo(Request $request): RedirectResponse
    {
        $user = $request->user();

        $phoneNumbers = $request->input('phone_numbers');
        if ($phoneNumbers !== null && is_array($phoneNumbers)) {
            $filtered = array_filter($phoneNumbers, function ($item) {
                return ! empty($item['label']) || ! empty($item['phone']);
            });
            $processed = array_map(function ($item) {
                if (! empty($item['label']) && $item['label'] === 'Other' && ! empty($item['label_custom'])) {
                    $item['label'] = $item['label_custom'];
                    unset($item['label_custom']);
                }

                return $item;
            }, $filtered);
            $user->phone = json_encode(array_values($processed));
        }

        $socialMedia = $request->input('social_media');
        if ($socialMedia !== null && is_array($socialMedia)) {
            $filtered = array_filter($socialMedia, function ($item) {
                return ! empty($item['social_title']) && ! empty($item['social_value']);
            });
            $processed = array_map(function ($item) {
                if (! empty($item['social_value'])) {
                    $url = trim($item['social_value']);
                    if (! preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\//', $url)) {
                        $url = 'https://'.$url;
                    }
                    $item['social_value'] = $url;
                }
                if (! empty($item['social_title']) && $item['social_title'] === 'Other' && ! empty($item['social_title_custom'])) {
                    $item['social_title'] = $item['social_title_custom'];
                    unset($item['social_title_custom']);
                }

                return $item;
            }, $filtered);
            $user->social_links = json_encode(array_values($processed));
        }

        $user->save();

        AuditService::log(AuditAction::ProfileUpdate, $user->id, [
            'changed_fields' => array_keys($user->getChanges()),
        ]);

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

    protected function getDisplayNameOptions($user): array
    {
        return $user->getDisplayNameOptions();
    }
}
