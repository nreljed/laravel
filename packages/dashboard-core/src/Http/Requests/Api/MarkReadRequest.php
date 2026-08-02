<?php

namespace Nreljed\DashboardCore\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MarkReadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required|string',
        ];
    }
}
