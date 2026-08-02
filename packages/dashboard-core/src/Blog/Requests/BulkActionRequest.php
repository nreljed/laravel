<?php

namespace Nreljed\DashboardCore\Blog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:posts,id'],
            'action' => ['required', 'string', 'in:publish,draft,delete'],
        ];
    }
}
