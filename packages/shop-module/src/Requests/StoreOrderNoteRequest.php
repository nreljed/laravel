<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('order'));
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:5000',
            'is_public' => 'boolean',
        ];
    }
}
