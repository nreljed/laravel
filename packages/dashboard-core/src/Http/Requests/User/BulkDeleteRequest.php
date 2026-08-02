<?php

namespace Nreljed\DashboardCore\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array'],
            'ids.*' => ['exists:users,id'],
        ];
    }
}
