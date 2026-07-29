<?php

namespace App\TestPosts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:test_posts,id'],
            'action' => ['required', 'string', 'in:publish,draft,delete'],
        ];
    }
}
