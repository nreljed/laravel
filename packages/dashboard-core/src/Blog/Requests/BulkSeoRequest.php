<?php

namespace Nreljed\DashboardCore\Blog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkSeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('posts.create');
    }

    public function rules(): array
    {
        return [
            'post_ids' => ['required_without:all', 'array', 'min:1'],
            'post_ids.*' => ['integer', 'exists:posts,id'],
            'all' => ['required_without:post_ids', 'boolean'],
        ];
    }
}
