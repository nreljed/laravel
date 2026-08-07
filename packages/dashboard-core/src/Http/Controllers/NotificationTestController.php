<?php

namespace Nreljed\DashboardCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\DashboardCore\Http\Requests\Notification\SendTestNotificationRequest;
use Nreljed\DashboardCore\Http\Requests\Notification\SendToAllRequest;
use Nreljed\DashboardCore\Models\User;
use Nreljed\DashboardCore\Notifications\AppNotification;
use Nreljed\DashboardCore\Services\NotificationService;
use Nreljed\DashboardCore\Services\NotificationTypeRegistry;
use Nreljed\DashboardCore\Services\SettingsService;

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

        return Inertia::render('dashboard/notifications/test', [
            'emailConfigured' => $fromEmail !== '',
            'fromEmail' => $fromEmail,
            'fromName' => $fromName,
            'notificationTableExists' => Schema::hasTable('notifications'),
            'emailTemplates' => $templates,
        ]);
    }

    public function sendTest(SendTestNotificationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        $channels = $data['channels'] ?? null;
        $channelsArray = $channels ? json_decode($channels, true) : ['in_app'];

        $notification = new AppNotification($data['type'], [
            'title' => $data['title'],
            'message' => $data['message'],
            'link' => $data['link'] ?? '',
            'severity' => $data['severity'] ?? 'info',
            'module' => $data['module'] ?? 'core',
        ]);

        $emailQueued = false;
        foreach ($channelsArray as $channel) {
            if ($channel === 'email') {
                $this->notificationService->notifyViaPreferences($user, $data['type'], $notification, ['email']);
                $emailQueued = true;
            } else {
                $this->notificationService->notifyViaPreferences($user, $data['type'], $notification, ['in_app']);
            }
        }

        return response()->json([
            'id' => $notification->id ?: uniqid(),
            'emailQueued' => $emailQueued,
        ]);
    }

    public function sendToAll(SendToAllRequest $request): JsonResponse
    {
        $data = $request->validated();

        $now = now();
        $type = AppNotification::class;
        $count = 0;

        User::chunk(100, function ($users) use ($data, $now, $type, &$count) {
            $rows = [];
            foreach ($users as $user) {
                $payload = array_merge($data, [
                    'type' => $data['type'],
                    'title' => $data['title'],
                    'severity' => $data['severity'] ?? 'info',
                    'module' => $data['module'] ?? 'core',
                    'link' => $data['link'] ?? '',
                    'message' => $data['message'] ?? '',
                ]);
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'type' => $type,
                    'notifiable_id' => $user->id,
                    'notifiable_type' => $user::class,
                    'data' => json_encode($payload),
                    'title' => $data['title'],
                    'message' => $data['message'] ?? '',
                    'link' => $data['link'] ?? '',
                    'severity' => $data['severity'] ?? 'info',
                    'module' => $data['module'] ?? 'core',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $count++;
            }
            if (! empty($rows)) {
                \DB::table('notifications')->insert($rows);
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
