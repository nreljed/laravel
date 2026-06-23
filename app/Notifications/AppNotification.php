<?php

namespace App\Notifications;

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
        ]);
    }

    public function toArray($notifiable): array
    {
        return $this->data;
    }
}
