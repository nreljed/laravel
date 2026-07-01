<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class DeleteAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'in:delete my account'],
            'current_password' => ['required', 'current_password'],
        ];
    }
}
