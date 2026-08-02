<?php

namespace Nreljed\DashboardCore\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class BulkNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['required', 'string', 'uuid'],
        ];
    }
}
