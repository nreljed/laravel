<?php

namespace Nreljed\DashboardCore\Http\Controllers;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\DashboardCore\Ai\Models\AiProvider;
use Nreljed\DashboardCore\Http\Requests\Account\DeleteAccountRequest;
use Nreljed\DashboardCore\Http\Requests\Account\UpdateContactRequest;
use Nreljed\DashboardCore\Http\Requests\Account\UpdateEmailRequest;
use Nreljed\DashboardCore\Http\Requests\Account\UpdateNotificationPreferencesRequest;
use Nreljed\DashboardCore\Http\Requests\Account\UpdatePasswordRequest;
use Nreljed\DashboardCore\Http\Requests\Account\VerifyDeletePasswordRequest;
use Nreljed\DashboardCore\Models\NotificationPreference;
use Nreljed\DashboardCore\Services\AccountService;
use Nreljed\DashboardCore\Services\NotificationTypeRegistry;
use Nreljed\DashboardCore\Services\SettingsService;

class UserAccountController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
    ) {}

    public function index()
    {
        return redirect()->route('dashboard.account.page.profile');
    }

    public function profile()
    {
        $user = request()->user();

        return Inertia::render('dashboard/account/profile', [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'display_name' => $user->display_name,
                'display_name_options' => $user->getDisplayNameOptions(),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'bio' => $user->bio,
                'url' => $user->url,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'contact_email' => $user->contact_email,
                'phone_numbers' => $user->phone ?? [],
                'social_media' => $user->social_links ?? [],
                'avatar' => $user->avatar_url,
                'user_registered' => $user->created_at,
                'roles' => $user->roles->pluck('name')->toArray(),
                'billing_address' => $user->billing_address,
                'shipping_address' => $user->shipping_address,
            ],
            'mustVerifyEmail' => $user instanceof MustVerifyEmail
                && SettingsService::get('require_email_verification', '0') === '1',
            'status' => session('status'),
        ]);
    }

    public function security()
    {
        $user = request()->user();

        return Inertia::render('dashboard/account/security', [
            'user' => [
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
            ],
            'mustVerifyEmail' => $user instanceof MustVerifyEmail
                && SettingsService::get('require_email_verification', '0') === '1',
            'status' => session('status'),
        ]);
    }

    public function notifications()
    {
        $user = request()->user();

        try {
            $typeRegistry = app(NotificationTypeRegistry::class);
            $notificationTypes = $typeRegistry->groups();
        } catch (\Exception) {
            $notificationTypes = [];
        }

        $preferences = NotificationPreference::where('user_id', $user->id)
            ->get()
            ->groupBy('type')
            ->map(fn ($items) => $items->pluck('enabled', 'channel')->toArray());

        return Inertia::render('dashboard/account/notifications', [
            'notificationTypes' => $notificationTypes,
            'preferences' => $preferences,
            'status' => session('status'),
        ]);
    }

    public function integrations()
    {
        $user = request()->user();

        $supported = AiProvider::supportedProviders();

        $aiProviders = $user->aiProviders()
            ->get()
            ->map(fn ($provider) => [
                'id' => $provider->id,
                'provider' => $provider->provider,
                'provider_name' => isset($supported[$provider->provider]) ? $supported[$provider->provider]['name'] : $provider->provider,
                'masked_key' => $provider->getMaskedKey(),
                'preferred_model' => $provider->preferred_model,
                'is_active' => $provider->is_active,
                'created_at' => $provider->created_at?->toDateString(),
            ]);

        return Inertia::render('dashboard/account/integrations', [
            'aiProviders' => $aiProviders,
            'supportedProviders' => AiProvider::supportedProviders(),
            'status' => session('status'),
        ]);
    }

    public function advanced()
    {
        return Inertia::render('dashboard/account/advanced', [
            'status' => session('status'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'display_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'url' => 'nullable|url|max:255',
            'contact_email' => 'nullable|email|max:255',
        ]);

        $this->accountService->updatePersonalInfo($request->user(), $validated);

        return redirect()->route('dashboard.account.page.profile')->with('status', 'Profile updated successfully.');
    }

    public function updateContact(UpdateContactRequest $request)
    {
        $this->accountService->updateContactInfo(
            $request->user(),
            $request->validated('phone_numbers'),
            $request->validated('social_media'),
        );

        return redirect()->route('dashboard.account.page.profile')->with('status', 'Contact info updated successfully.');
    }

    public function updateEmail(UpdateEmailRequest $request)
    {
        $result = $this->accountService->updateEmail(
            $request->user(),
            $request->validated('email'),
        );

        if (! $result['success']) {
            return back()->withErrors(['email' => $result['message']]);
        }

        return redirect()->back()->with('status', $result['message']);
    }

    public function updatePassword(UpdatePasswordRequest $request)
    {
        $result = $this->accountService->updatePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        if (! $result['success']) {
            return back()->withErrors(['password' => $result['message']]);
        }

        return redirect()->back()->with('status', $result['message']);
    }

    public function destroy(DeleteAccountRequest $request)
    {
        $user = $request->user();

        $feedback = $request->validated('feedback');
        if ($feedback) {
            \Log::info('Account deletion feedback', [
                'user_id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'feedback' => $feedback,
            ]);
        }

        auth()->logout();

        $this->accountService->deleteAccount($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function verifyDeletePassword(VerifyDeletePasswordRequest $request)
    {
        return response()->json(['verified' => true]);
    }

    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request)
    {
        foreach ($request->validated('preferences') as $pref) {
            NotificationPreference::updateOrCreate(
                [
                    'user_id' => $request->user()->id,
                    'type' => $pref['type'],
                    'channel' => $pref['channel'],
                ],
                ['enabled' => $pref['enabled']]
            );
        }

        return redirect()->back()->with('status', 'Notification preferences updated.');
    }

    public function updateAddress(Request $request)
    {
        $validated = $request->validate([
            'billing_address' => 'nullable|array',
            'billing_address.first_name' => 'nullable|string|max:255',
            'billing_address.last_name' => 'nullable|string|max:255',
            'billing_address.company' => 'nullable|string|max:255',
            'billing_address.address_line1' => 'nullable|string|max:255',
            'billing_address.address_line2' => 'nullable|string|max:255',
            'billing_address.city' => 'nullable|string|max:255',
            'billing_address.state' => 'nullable|string|max:255',
            'billing_address.postcode' => 'nullable|string|max:20',
            'billing_address.country' => 'nullable|string|max:2',
            'billing_address.phone' => 'nullable|string|max:50',
            'shipping_address' => 'nullable|array',
            'shipping_address.first_name' => 'nullable|string|max:255',
            'shipping_address.last_name' => 'nullable|string|max:255',
            'shipping_address.company' => 'nullable|string|max:255',
            'shipping_address.address_line1' => 'nullable|string|max:255',
            'shipping_address.address_line2' => 'nullable|string|max:255',
            'shipping_address.city' => 'nullable|string|max:255',
            'shipping_address.state' => 'nullable|string|max:255',
            'shipping_address.postcode' => 'nullable|string|max:20',
            'shipping_address.country' => 'nullable|string|max:2',
            'shipping_address.phone' => 'nullable|string|max:50',
        ]);

        $request->user()->update([
            'billing_address' => $validated['billing_address'] ?? null,
            'shipping_address' => $validated['shipping_address'] ?? null,
        ]);

        return redirect()->back()->with('status', 'Address updated.');
    }
}
