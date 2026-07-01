<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
{
    public function rules(): array
    {
        $file = $this->file('logo');
        $isSvg = $file && $file->getClientOriginalExtension() === 'svg';

        return [
            'logo' => $isSvg
                ? ['required', 'mimes:svg', 'max:10240']
                : ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:10240', 'dimensions:min_width=100,min_height=100,max_width=4096,max_height=4096'],
        ];
    }
}
