<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\NotificationService;
use App\Services\NotificationTypeRegistry;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class NotificationTestController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    public function index(): Response
    {
        $emailOptions = SettingsService::getGroup('email');
        $fromEmail = $emailOptions['from_email'] ?? '';
        $fromName = $emailOptions['from_name'] ?? '';

        $registry = app(NotificationTypeRegistry::class);
        $templates = [];
        foreach ($registry->all() as $key => $type) {
            if (! empty($type['email_template'])) {
                $templates[] = [
                    'value' => $type['email_template'],
                    'label' => $type['label'],
                    'group' => $type['group'] ?? 'General',
                ];
            }
        }

        return Inertia::render('Notifications/Test', [
            'emailConfigured' => $fromEmail !== '',
            'fromEmail' => $fromEmail,
            'fromName' => $fromName,
            'notificationTableExists' => Schema::hasTable('notifications'),
            'emailTemplates' => $templates,
        ]);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'link' => 'nullable|string|max:500',
            'severity' => 'nullable|string|in:info,warning,urgent,critical',
            'channels' => 'nullable|string',
        ]);

        $user = $request->user();
        $channels = $data['channels'] ?? null;
        $channelsArray = $channels ? json_decode($channels, true) : ['in_app'];

        $notification = new AppNotification($data['type'], [
            'title' => $data['title'],
            'message' => $data['message'],
            'link' => $data['link'] ?? '',
            'severity' => $data['severity'] ?? 'info',
        ]);

        $emailQueued = false;
        foreach ($channelsArray as $channel) {
            if ($channel === 'email') {
                $this->notificationService->notifyViaPreferences($user, $data['type'], $notification, ['email']);
                $emailQueued = true;
            } else {
                $user->notify($notification);
            }
        }

        return response()->json([
            'id' => $notification->id ?? uniqid(),
            'emailQueued' => $emailQueued,
        ]);
    }

    public function sendToAll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'link' => 'nullable|string|max:500',
            'severity' => 'nullable|string|in:info,warning,urgent,critical',
        ]);

        $notification = new AppNotification($data['type'], [
            'title' => $data['title'],
            'message' => $data['message'],
            'link' => $data['link'] ?? '',
            'severity' => $data['severity'] ?? 'info',
        ]);

        $count = 0;
        User::chunk(100, function ($users) use ($notification, &$count) {
            foreach ($users as $user) {
                $user->notify($notification);
                $count++;
            }
        });

        return response()->json(['count' => $count]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $request->user()->notifications()->delete();

        return response()->json(['ok' => true]);
    }
}
