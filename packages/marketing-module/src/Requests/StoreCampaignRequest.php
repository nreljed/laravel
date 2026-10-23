<?php

namespace Nreljed\MarketingModule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Enums\CampaignStatus;

class StoreCampaignRequest extends FormRequest
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
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:5000',
            'objective' => ['required', Rule::enum(CampaignObjective::class)],
            'status' => ['required', Rule::enum(CampaignStatus::class)],
            'starts_at' => 'nullable|date',
            'ends_at' => [
                'nullable',
                'date',
                Rule::when($this->filled('starts_at'), 'after_or_equal:starts_at'),
            ],
            'metadata' => 'nullable|array',
        ];
    }
}
