<?php

namespace Nreljed\DashboardCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DatabaseResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAdmin() && $user->can('manage-options');
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', Rule::in(['RESET'])],
            'password' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! Hash::check($value, $this->user()->password)) {
                    $fail('The password does not match your current password.');
                }
            }],
            'purge_media' => ['sometimes', 'boolean'],
        ];
    }
}
