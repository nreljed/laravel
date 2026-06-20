<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'display_name' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'social_links' => ['nullable', 'array'],
            'social_links.twitter' => ['nullable', 'string', 'max:255'],
            'social_links.facebook' => ['nullable', 'string', 'max:255'],
            'social_links.instagram' => ['nullable', 'string', 'max:255'],
            'social_links.linkedin' => ['nullable', 'string', 'max:255'],
            'social_links.tiktok' => ['nullable', 'string', 'max:255'],
            'social_links.youtube' => ['nullable', 'string', 'max:255'],
            'social_links.whatsapp' => ['nullable', 'string', 'max:255'],
        ];
    }
}
