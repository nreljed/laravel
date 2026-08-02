<?php

namespace Nreljed\DashboardCore\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class BulkActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:users,id'],
        ];
    }
}
