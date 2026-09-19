<?php

namespace Nreljed\Platform\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class RestoreAccountDataRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'backup' => ['required', 'file', 'mimes:json,text/plain', 'max:20480'],
            'confirmation' => ['required', 'string', 'in:restore my data'],
        ];
    }

    public function messages(): array
    {
        return [
            'backup.required' => 'Please attach the backup file you exported earlier.',
            'backup.file' => 'The backup must be a file upload.',
            'backup.mimes' => 'The backup must be a JSON file.',
            'backup.max' => 'The backup file may not exceed 20 MB.',
            'confirmation.in' => 'Type "restore my data" to confirm the restore.',
        ];
    }
}
