<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Services\NotificationTypeRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationPreferenceController extends Controller
{
    protected NotificationTypeRegistry $typeRegistry;

    public function __construct(NotificationTypeRegistry $typeRegistry)
    {
        $this->typeRegistry = $typeRegistry;
    }

    public function edit(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Account/Edit', [
            'notificationTypes' => $this->typeRegistry->groups(),
            'preferences' => NotificationPreference::where('user_id', $user->id)
                ->get()
                ->groupBy('type')
                ->map(fn($items) => $items->pluck('enabled', 'channel')->toArray()),
        ]);
    }

    public function update(Request $request)
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
