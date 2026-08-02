<?php

namespace Nreljed\DashboardCore\Ai\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Nreljed\DashboardCore\Ai\Models\AiProvider;

class TestAiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'in:'.implode(',', array_keys(AiProvider::supportedProviders()))],
            'api_key' => ['required', 'string', 'min:10'],
            'preferred_model' => ['nullable', 'string', 'max:255'],
        ];
    }
}
