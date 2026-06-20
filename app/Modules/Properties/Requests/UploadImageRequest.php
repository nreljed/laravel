<?php

namespace App\Modules\Properties\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|image|mimes:jpeg,png,webp|max:5120',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500',
            'is_featured' => 'nullable|boolean',
        ];
    }
}
