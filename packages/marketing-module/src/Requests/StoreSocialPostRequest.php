<?php

namespace Nreljed\MarketingModule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSocialPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $media = $this->input('media');

        if (! is_array($media)) {
            return;
        }

        $this->merge([
            'media' => array_values(array_map(
                static fn ($item): array => is_array($item) ? $item : ['url' => $item],
                $media,
            )),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->user()?->getAuthIdentifier();

        return [
            'body' => 'required|string|max:63206',
            'link' => 'nullable|url|max:2048',
            'hashtags' => 'nullable|string|max:255',
            'scheduled_at' => 'nullable|date',
            'requires_approval' => 'sometimes|boolean',
            'account_ids' => 'required|array|min:1',
            'account_ids.*' => [
                'integer',
                Rule::exists('social_accounts', 'id')->where('user_id', $userId),
            ],
            'media' => 'nullable|array|max:4',
            'media.*.url' => 'required|string|max:2048',
            'media.*.alt' => 'nullable|string|max:255',
            'media.*.order' => 'nullable|integer|min:0',
        ];
    }
}
