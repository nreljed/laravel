<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    protected $fillable = [
        'id', 'type', 'notifiable_type', 'notifiable_id',
        'data', 'title', 'message', 'link', 'severity', 'module',
        'read_at', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
