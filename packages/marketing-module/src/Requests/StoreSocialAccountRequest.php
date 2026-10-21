<?php

namespace Nreljed\MarketingModule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;

class StoreSocialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_name' => 'required|string|max:255',
            'username' => 'nullable|string|max:255',
            'board_id' => 'nullable|string|max:191',
            'status' => ['nullable', Rule::enum(SocialAccountStatus::class)],
        ];
    }
}
