<?php

namespace Nreljed\DashboardCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFaviconRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'favicon' => ['required', 'image', 'mimes:ico,png', 'max:5120', 'dimensions:square,min_width=16,min_height=16,max_width=1024,max_height=1024'],
        ];
    }
}
