<?php

namespace Nreljed\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Legacy form request for the contact form.
 *
 * The collect path no longer type-hints this class — FormSubmissionService
 * builds its rules from the `contact` entry in config/forms.php. This request
 * is retained so a consumer type-hinting it keeps working, and
 * FormSubmissionsContractTest pins its rules to the registry's output so the
 * two can never silently drift.
 */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The honeypot field (`website`) is intentionally absent from these rules:
     * the controller discards honeypot submissions silently so a bot cannot
     * learn which field trapped it.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email address',
            'subject' => 'subject',
            'message' => 'message',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.min' => 'Please tell us a little more (at least 10 characters).',
            'message.max' => 'Please keep your message under 5,000 characters.',
        ];
    }
}
