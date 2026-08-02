<?php

namespace Nreljed\DashboardCore\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class VerifyDeletePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
        ];
    }
}
