<?php

namespace Nreljed\MarketingModule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;

class StoreWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);

            return;
        }

        if ($this->route('record') === null) {
            $this->merge(['is_active' => true]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191',
            'description' => 'nullable|string|max:5000',
            'trigger' => ['required', Rule::enum(WorkflowTrigger::class)],
            'action' => ['required', Rule::enum(WorkflowAction::class)],
            'condition_tag_id' => [
                'nullable',
                'integer',
                Rule::exists('tags', 'id'),
            ],
            'tag_id' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('action'), [
                    WorkflowAction::AddTag->value,
                    WorkflowAction::RemoveTag->value,
                ], true)),
                'nullable',
                'integer',
                Rule::exists('tags', 'id'),
            ],
            'contact_status' => [
                Rule::requiredIf(fn (): bool => $this->input('action') === WorkflowAction::SetStatus->value),
                'nullable',
                Rule::enum(ContactStatus::class),
            ],
            'webhook_url' => [
                Rule::requiredIf(fn (): bool => $this->input('action') === WorkflowAction::Webhook->value),
                'nullable',
                'url',
                'max:2048',
            ],
            'is_active' => 'boolean',
            'steps' => 'nullable|array',
        ];
    }

    /**
     * Keep a publishing event from being pointed at a contact-only action.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $trigger = WorkflowTrigger::tryFrom((string) $this->input('trigger'));
            $action = WorkflowAction::tryFrom((string) $this->input('action'));

            if ($trigger === null || $action === null) {
                return;
            }

            if (! $trigger->isContactEvent() && $action->requiresContact()) {
                $validator->errors()->add(
                    'action',
                    'A post published workflow can only send a webhook.',
                );
            }

            if (! $trigger->isContactEvent() && $this->input('condition_tag_id') !== null) {
                $validator->errors()->add(
                    'condition_tag_id',
                    'A post published workflow has no contact to check a tag against.',
                );
            }
        });
    }
}
