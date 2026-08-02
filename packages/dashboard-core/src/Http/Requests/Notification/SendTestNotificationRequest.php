<?php

namespace Nreljed\DashboardCore\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class SendTestNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'type' => 'required|string',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'link' => 'nullable|string|max:500',
            'severity' => 'nullable|string|in:info,warning,urgent,critical',
            'module' => 'nullable|string|max:100',
            'channels' => 'nullable|string',
        ];
    }
}
