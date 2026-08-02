<?php

namespace Nreljed\DashboardCore\Notifications;

use Illuminate\Notifications\Notification;

class AppNotification extends Notification
{
    public function __construct(
        protected string $type,
        protected array $data = [],
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return array_merge($this->data, [
            'type' => $this->type,
            'title' => $this->data['title'] ?? $this->type,
            'severity' => $this->data['severity'] ?? 'info',
            'module' => $this->data['module'] ?? 'core',
            'link' => $this->data['link'] ?? $this->data['url'] ?? '',
            'message' => $this->data['message'] ?? $this->data['body'] ?? '',
        ]);
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
