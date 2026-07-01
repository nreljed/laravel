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
            'mustVerifyEmail' => $user instanceof MustVerifyEmail
                && SettingsService::get('require_email_verification', '0') === '1',
            'notificationTypes' => $notificationTypes,
            'preferences' => $preferences,
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
}
