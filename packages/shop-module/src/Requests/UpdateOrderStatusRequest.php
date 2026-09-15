<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:draft,pending,on-hold,confirmed,processing,completed,cancelled,refunded,failed,trash',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
