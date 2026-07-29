<?php

namespace App\Ai\Requests;

use App\Ai\Models\AiProvider;
use Illuminate\Foundation\Http\FormRequest;

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
