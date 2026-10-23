<?php

namespace Nreljed\MarketingModule\Services;

use Closure;
use Illuminate\Support\Facades\Http;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Models\WorkflowRun;
use Throwable;

/**
 * The automation runtime.
 *
 * It evaluates the active rules for one event and writes a run record for each
 * outcome. A failed webhook or a missing tag is recorded, never thrown back
 * into the request that created the contact or published the post.
 */
class WorkflowEngine
{
    /**
     * @param  Closure(array<string, mixed>): void|null  $webhookSender
     */
    public function __construct(protected ?Closure $webhookSender = null) {}

    public function contactCreated(Contact $contact): void
    {
        $this->runForContact(WorkflowTrigger::ContactCreated, $contact);
    }

    public function postPublished(SocialPost $post): void
    {
        $workflows = Workflow::query()
            ->where('trigger', WorkflowTrigger::PostPublished->value)
            ->where('is_active', true)
            ->get();

        foreach ($workflows as $workflow) {
            $this->execute($workflow, $post, [
                'event' => WorkflowTrigger::PostPublished->value,
                'post' => [
                    'id' => $post->id,
                    'body' => mb_substr((string) $post->body, 0, 280),
                    'status' => $post->status?->value,
                    'campaign_id' => $post->campaign_id,
                    'published_at' => $post->published_at?->toIso8601String(),
                ],
            ]);
        }
    }

    protected function runForContact(WorkflowTrigger $trigger, Contact $contact): void
    {
        $contact->loadMissing('tags');

        $workflows = Workflow::query()
            ->where('trigger', $trigger->value)
            ->where('is_active', true)
            ->get();

        foreach ($workflows as $workflow) {
            if ($workflow->condition_tag_id !== null
                && ! $contact->tags->contains('id', $workflow->condition_tag_id)) {
                continue;
            }

            $this->execute($workflow, $contact, [
                'event' => $trigger->value,
                'contact' => [
                    'id' => $contact->id,
                    'email' => $contact->email,
                    'name' => $contact->displayName(),
                    'status' => $contact->status?->value,
                ],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function execute(Workflow $workflow, Contact|SocialPost $subject, array $payload): void
    {
        try {
            match ($workflow->action) {
                WorkflowAction::AddTag => $this->applyTag($workflow, $subject, true),
                WorkflowAction::RemoveTag => $this->applyTag($workflow, $subject, false),
                WorkflowAction::SetStatus => $this->setStatus($workflow, $subject),
                WorkflowAction::Webhook => $this->sendWebhook($workflow, $payload),
            };

            $this->record($workflow, $subject, WorkflowRunStatus::Success, null, $payload);
        } catch (Throwable $e) {
            $this->record($workflow, $subject, WorkflowRunStatus::Failed, $e->getMessage(), $payload);
        }
    }

    protected function applyTag(Workflow $workflow, Contact|SocialPost $subject, bool $attach): void
    {
        if (! $subject instanceof Contact || $workflow->tag_id === null) {
            throw new \RuntimeException('This action needs a contact and a tag.');
        }

        $attach
            ? $subject->tags()->syncWithoutDetaching([$workflow->tag_id])
            : $subject->tags()->detach($workflow->tag_id);
    }

    protected function setStatus(Workflow $workflow, Contact|SocialPost $subject): void
    {
        if (! $subject instanceof Contact) {
            throw new \RuntimeException('This action needs a contact.');
        }

        if ($workflow->contact_status === null || $workflow->contact_status === '') {
            throw new \RuntimeException('This action needs a contact status.');
        }

        $subject->forceFill(['status' => $workflow->contact_status])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sendWebhook(Workflow $workflow, array $payload): void
    {
        if ($workflow->webhook_url === null || $workflow->webhook_url === '') {
            throw new \RuntimeException('This action needs a webhook URL.');
        }

        $body = $payload + [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'trigger' => $workflow->trigger?->value,
                'action' => $workflow->action?->value,
            ],
            'occurred_at' => now()->toIso8601String(),
        ];

        if ($this->webhookSender !== null) {
            ($this->webhookSender)($body);

            return;
        }

        Http::post($workflow->webhook_url, $body)->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function record(
        Workflow $workflow,
        Contact|SocialPost $subject,
        WorkflowRunStatus $status,
        ?string $message,
        array $payload,
    ): void {
        WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'event' => $payload['event'] ?? (string) $workflow->trigger?->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'status' => $status,
            'message' => $message,
            'payload' => $payload,
            'ran_at' => now(),
        ]);

        if ($status === WorkflowRunStatus::Success) {
            $workflow->forceFill([
                'run_count' => $workflow->run_count + 1,
                'last_run_at' => now(),
            ])->save();
        }
    }
}
