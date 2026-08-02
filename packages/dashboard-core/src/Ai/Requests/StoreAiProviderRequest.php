<?php

namespace Nreljed\DashboardCore\Ai\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Nreljed\DashboardCore\Ai\Models\AiProvider;

class StoreAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supportedProviders = array_keys(AiProvider::supportedProviders());

        return [
            'provider' => ['required', 'string', 'in:'.implode(',', $supportedProviders)],
            'api_key' => ['required', 'string', 'min:10', 'max:500'],
            'preferred_model' => ['nullable', 'string', 'max:255'],
        ];
    }
}
