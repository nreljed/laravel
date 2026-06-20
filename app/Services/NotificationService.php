<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Modules\ModuleRegistry;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationService
{
    protected NotificationTypeRegistry $typeRegistry;
    protected ModuleRegistry $moduleRegistry;
    protected MailService $mailService;

    public function __construct(
        NotificationTypeRegistry $typeRegistry,
        ModuleRegistry $moduleRegistry,
        MailService $mailService,
    ) {
        $this->typeRegistry = $typeRegistry;
        $this->moduleRegistry = $moduleRegistry;
        $this->mailService = $mailService;
    }

    public function notifyViaPreferences($notifiable, string $type, Notification $notification, array $channels = null): void
    {
        $typeConfig = $this->typeRegistry->get($type);
        if (!$typeConfig) {
            NotificationFacade::send($notifiable, $notification);
            return;
        }

        $channels = $channels ?? ($typeConfig['default_channels'] ?? ['in_app']);
        $allowedChannels = [];

        foreach ($channels as $channel) {
            $pref = NotificationPreference::where('user_id', $notifiable->id)
                ->where('type', $type)
                ->where('channel', $channel)
                ->first();

            $defaultEnabled = $typeConfig['default_enabled'] ?? true;

            if ($pref === null) {
                if ($defaultEnabled) {
                    $allowedChannels[] = $channel;
                }
            } elseif ($pref->enabled) {
                $allowedChannels[] = $channel;
            }
        }

        if (!empty($allowedChannels)) {
            $notifiable->notify($notification);
        }

        if (in_array('email', $allowedChannels, true)) {
            $template = $typeConfig['email_template'] ?? null;
            if ($template) {
                $this->sendNotificationEmail($notifiable, $type, $notification, $template, $typeConfig);
            }
        }
    }

    protected function sendNotificationEmail($notifiable, string $type, Notification $notification, string $template, array $typeConfig): void
    {
        try {
            $data = method_exists($notification, 'toArray')
                ? $notification->toArray($notifiable)
                : [];

            $label = $typeConfig['label'] ?? $type;
            $user = $notifiable;

            $this->mailService->send(
                $user->email,
                '[' . config('app.name') . '] ' . $label,
                $template,
                array_merge($data, [
                    'userName' => $user->display_name ?: $user->name,
                    'userEmail' => $user->email,
                    'notificationTitle' => $data['title'] ?? $label,
                    'notificationMessage' => $data['message'] ?? '',
                    'notificationLink' => $data['link'] ?? '',
                    'siteName' => config('app.name'),
                ])
            );
        } catch (\Throwable $e) {
            Log::error('[NotificationService] Failed to send notification email for type: ' . $type . ' | Error: ' . $e->getMessage());
        }
    }

    public function purge(int $retentionDays = 90): int
    {
        $cutoff = now()->subDays($retentionDays);

        $deleted = \DB::table('notifications')
            ->where('created_at', '<', $cutoff)
            ->delete();

        Log::info('[NotificationService] Purged ' . $deleted . ' notifications older than ' . $retentionDays . ' days.');

        return $deleted;
    }
}
