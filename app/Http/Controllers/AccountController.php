<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\DeleteAccountRequest;
use App\Http\Requests\Account\UpdateEmailRequest;
use App\Http\Requests\Account\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\VerifyDeletePasswordRequest;
use App\Models\NotificationPreference;
use App\Services\AccountService;
use App\Services\NotificationTypeRegistry;
use App\Services\SettingsService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function __construct(
        protected AccountService $accountService,
    ) {}

    public function edit()
    {
        $user = request()->user();

        try {
            $typeRegistry = app(NotificationTypeRegistry::class);
            $notificationTypes = $typeRegistry->groups();
        } catch (\Exception $e) {
            $notificationTypes = [];
        }

        $preferences = NotificationPreference::where('user_id', $user->id)
            ->get()
            ->groupBy('type')
            ->map(fn ($items) => $items->pluck('enabled', 'channel')->toArray());

        $tokens = $user->tokens()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'created_at' => $token->created_at->toDateString(),
            ]);

        return Inertia::render('dashboard/account/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'avatar' => $user->avatar_url,
                'user_login' => $user->email,
                'billing_address' => $user->billing_address,
                'shipping_address' => $user->shipping_address,
            ],
            'mustVerifyEmail' => $user instanceof MustVerifyEmail
                && SettingsService::get('require_email_verification', '0') === '1',
            'notificationTypes' => $notificationTypes,
            'preferences' => $preferences,
            'tokens' => $tokens,
        ]);
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

    public function tokens(Request $request)
    {
        $tokens = $request->user()->tokens()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'created_at' => $token->created_at->toDateString(),
            ]);

        return response()->json(['tokens' => $tokens]);
    }

    public function createToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $token = $request->user()->createToken($validated['name']);

        return redirect()->back()->with([
            'flash' => ['success' => 'Token created.'],
            'createdToken' => $token->plainTextToken,
        ]);
    }

    public function deleteToken(Request $request, int $tokenId)
    {
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            return redirect()->back()->withErrors(['token' => 'Token not found.']);
        }

        $token->delete();

        return redirect()->back()->with('flash', ['success' => 'Token revoked.']);
    }
}
