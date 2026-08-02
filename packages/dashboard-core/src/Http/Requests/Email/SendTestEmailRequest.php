<?php

namespace Nreljed\DashboardCore\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class SendTestEmailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'template' => 'required|string',
            'to' => 'nullable|email',
        ];
    }
}
