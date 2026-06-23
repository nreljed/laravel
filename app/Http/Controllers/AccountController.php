<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Modules\ModuleRegistry;
use App\Services\AccountService;
use App\Services\AuditService;
use App\Services\NotificationTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use App\Services\SettingsService;
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
            ->map(fn($items) => $items->pluck('enabled', 'channel')->toArray());

        return Inertia::render('Account/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'display_name' => $user->display_name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'avatar' => $user->avatar_url,
                'user_login' => $user->email,
            ],
            'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail
                && SettingsService::get('require_email_verification', '0') === '1',
            'notificationTypes' => $notificationTypes,
            'preferences' => $preferences,
        ]);
    }

    public function updateEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $request->user()->id],
            'current_password' => ['required', 'current_password'],
        ]);

        $result = $this->accountService->updateEmail(
            $request->user(),
            $validated['email'],
        );

        if (!$result['success']) {
            return back()->withErrors(['email' => $result['message']]);
        }

        return redirect()->back()->with('status', $result['message']);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $result = $this->accountService->updatePassword(
            $request->user(),
            $validated['current_password'],
            $validated['password'],
        );

        if (!$result['success']) {
            return back()->withErrors(['password' => $result['message']]);
        }

        return redirect()->back()->with('status', $result['message']);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'confirmation' => ['required', 'string', 'in:delete my account'],
            'current_password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        auth()->logout();

        $this->accountService->deleteAccount($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function verifyDeletePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        return response()->json(['verified' => true]);
    }

    public function updateNotificationPreferences(Request $request)
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', 'string'],
            'preferences.*.channel' => ['required', 'string', 'in:in_app,email'],
            'preferences.*.enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated['preferences'] as $pref) {
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
}
