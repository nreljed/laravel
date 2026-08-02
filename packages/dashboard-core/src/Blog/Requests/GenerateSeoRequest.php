<?php

namespace Nreljed\DashboardCore\Blog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSeoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('posts.create');
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'keyword' => ['nullable', 'string', 'max:100'],
        ];
    }
}
