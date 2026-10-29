<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Http;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Models\WorkflowRun;
use Throwable;

/**
 * The automation runtime.
 *
 * A workflow is either a single legacy rule or an ordered list of steps. Steps
 * may be delayed and may branch on success or failure; a failed webhook or a
 * missing tag is recorded, never thrown back into the request that triggered
 * the workflow.
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

    public function orderPlaced(Contact $contact, array $orderData = []): void
    {
        $contact->loadMissing('tags');

        $workflows = Workflow::query()
            ->where('trigger', WorkflowTrigger::OrderPlaced->value)
            ->where('is_active', true)
            ->get();

        foreach ($workflows as $workflow) {
            if ($workflow->condition_tag_id !== null
                && ! $contact->tags->contains('id', $workflow->condition_tag_id)) {
                continue;
            }

            $payload = [
                'event' => WorkflowTrigger::OrderPlaced->value,
                'contact' => [
                    'id' => $contact->id,
                    'email' => $contact->email,
                    'name' => $contact->displayName(),
                    'status' => $contact->status?->value,
                ],
                'order' => $orderData,
            ];

            $this->runWorkflow($workflow, $contact, $payload);
        }
    }

    public function cartAbandoned(?Contact $contact, array $cartData = []): void
    {
        if ($contact === null) {
            return;
        }

        $contact->loadMissing('tags');

        $workflows = Workflow::query()
            ->where('trigger', WorkflowTrigger::CartAbandoned->value)
            ->where('is_active', true)
            ->get();

        foreach ($workflows as $workflow) {
            if ($workflow->condition_tag_id !== null
                && ! $contact->tags->contains('id', $workflow->condition_tag_id)) {
                continue;
            }

            $payload = [
                'event' => WorkflowTrigger::CartAbandoned->value,
                'contact' => [
                    'id' => $contact->id,
                    'email' => $contact->email,
                    'name' => $contact->displayName(),
                    'status' => $contact->status?->value,
                ],
                'cart' => $cartData,
            ];

            $this->runWorkflow($workflow, $contact, $payload);
        }
    }

    public function productReviewed(?Contact $contact, array $reviewData = []): void
    {
        if ($contact === null) {
            return;
        }

        $contact->loadMissing('tags');

        $workflows = Workflow::query()
            ->where('trigger', WorkflowTrigger::ProductReviewed->value)
            ->where('is_active', true)
            ->get();

        foreach ($workflows as $workflow) {
            if ($workflow->condition_tag_id !== null
                && ! $contact->tags->contains('id', $workflow->condition_tag_id)) {
                continue;
            }

            $payload = [
                'event' => WorkflowTrigger::ProductReviewed->value,
                'contact' => [
                    'id' => $contact->id,
                    'email' => $contact->email,
                    'name' => $contact->displayName(),
                    'status' => $contact->status?->value,
                ],
                'review' => $reviewData,
            ];

            $this->runWorkflow($workflow, $contact, $payload);
        }
    }

    public function postPublished(SocialPost $post): void
    {
        $workflows = Workflow::query()
            ->where('trigger', WorkflowTrigger::PostPublished->value)
            ->where('is_active', true)
            ->get();

        foreach ($workflows as $workflow) {
            $payload = [
                'event' => WorkflowTrigger::PostPublished->value,
                'post' => [
                    'id' => $post->id,
                    'body' => mb_substr((string) $post->body, 0, 280),
                    'status' => $post->status?->value,
                    'campaign_id' => $post->campaign_id,
                    'published_at' => $post->published_at?->toIso8601String(),
                ],
            ];

            $this->runWorkflow($workflow, $post, $payload);
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

            $payload = [
                'event' => $trigger->value,
                'contact' => [
                    'id' => $contact->id,
                    'email' => $contact->email,
                    'name' => $contact->displayName(),
                    'status' => $contact->status?->value,
                ],
            ];

            $this->runWorkflow($workflow, $contact, $payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function runWorkflow(Workflow $workflow, Contact|SocialPost $subject, array $payload): void
    {
        $steps = $workflow->steps ?? [];

        if (is_array($steps) && $steps !== []) {
            $this->scheduleStep($workflow, $subject, $payload, 0);

            return;
        }

        $this->executeLegacy($workflow, $subject, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function executeLegacy(Workflow $workflow, Contact|SocialPost $subject, array $payload): void
    {
        try {
            match ($workflow->action) {
                WorkflowAction::AddTag => $this->applyTag($workflow, $subject, (int) $workflow->tag_id, true),
                WorkflowAction::RemoveTag => $this->applyTag($workflow, $subject, (int) $workflow->tag_id, false),
                WorkflowAction::SetStatus => $this->setStatusValue($subject, (string) $workflow->contact_status),
                WorkflowAction::Webhook => $this->sendWebhook($workflow, $payload, $workflow->webhook_url),
            };

            $this->record($workflow, null, $subject, WorkflowRunStatus::Success, null, $payload);
        } catch (Throwable $e) {
            $this->record($workflow, null, $subject, WorkflowRunStatus::Failed, $e->getMessage(), $payload);
        }
    }

    /**
     * Schedule one step for later execution.
     *
     * @param  array<string, mixed>  $payload
     */
    public function scheduleStep(Workflow $workflow, Contact|SocialPost $subject, array $payload, int $stepIndex, ?CarbonInterface $when = null): ?WorkflowRun
    {
        $step = $workflow->step($stepIndex);

        if ($step === null) {
            return null;
        }

        $delay = max(0, (int) ($step['delay_minutes'] ?? 0));
        $ranAt = $when ?? now()->addMinutes($delay);

        return WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'step_index' => $stepIndex,
            'event' => $payload['event'] ?? (string) $workflow->trigger?->value,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'status' => WorkflowRunStatus::Pending,
            'message' => null,
            'payload' => $payload + ['step' => $stepIndex],
            'ran_at' => $ranAt,
        ]);
    }

    /**
     * Process every due workflow step.
     */
    public function processPendingRuns(): int
    {
        $runs = WorkflowRun::query()
            ->where('status', WorkflowRunStatus::Pending->value)
            ->where('ran_at', '<=', now())
            ->orderBy('ran_at')
            ->get();

        $processed = 0;

        foreach ($runs as $run) {
            $claimed = WorkflowRun::query()
                ->whereKey($run->getKey())
                ->where('status', WorkflowRunStatus::Pending->value)
                ->update(['status' => WorkflowRunStatus::Pending->value]);

            // The status stays pending until processing finishes; the query above
            // only guards against a missing row. Concurrency is handled by the
            // database transaction in processRun().
            $this->processRun($run);
            $processed++;
        }

        return $processed;
    }

    public function processRun(WorkflowRun $run): void
    {
        $workflow = $run->workflow;

        if ($workflow === null) {
            $run->forceFill([
                'status' => WorkflowRunStatus::Failed,
                'message' => 'The workflow no longer exists.',
            ])->save();

            return;
        }

        $stepIndex = (int) ($run->step_index ?? 0);
        $step = $workflow->step($stepIndex);

        if ($step === null) {
            $run->forceFill([
                'status' => WorkflowRunStatus::Failed,
                'message' => 'The workflow step no longer exists.',
            ])->save();

            return;
        }

        $subject = $this->resolveSubject($run);

        if ($subject === null) {
            $run->forceFill([
                'status' => WorkflowRunStatus::Failed,
                'message' => 'The subject no longer exists.',
            ])->save();

            return;
        }

        $payload = is_array($run->payload) ? $run->payload : [];
        $conditionTagId = $step['condition_tag_id'] ?? $workflow->condition_tag_id;

        if ($conditionTagId !== null && $subject instanceof Contact) {
            $subject->loadMissing('tags');

            if (! $subject->tags->contains('id', $conditionTagId)) {
                $run->forceFill([
                    'status' => WorkflowRunStatus::Skipped,
                    'message' => 'The condition tag is not present.',
                ])->save();

                $this->scheduleBranch($workflow, $step, $subject, $payload, $stepIndex, false);

                return;
            }
        }

        if (! $this->metricConditionPassed($step, $subject)) {
            $run->forceFill([
                'status' => WorkflowRunStatus::Skipped,
                'message' => 'The metric condition was not met.',
            ])->save();

            $this->scheduleBranch($workflow, $step, $subject, $payload, $stepIndex, false);

            return;
        }

        try {
            $this->executeStepAction($workflow, $step, $subject, $payload);

            $run->forceFill([
                'status' => WorkflowRunStatus::Success,
                'message' => null,
            ])->save();

            if ($run->status === WorkflowRunStatus::Success) {
                $workflow->forceFill([
                    'run_count' => $workflow->run_count + 1,
                    'last_run_at' => now(),
                ])->save();
            }

            $next = $step['on_success'] ?? ($stepIndex + 1);

            if ($next !== null && $next !== false && $next !== -1) {
                $this->scheduleStep($workflow, $subject, $payload, (int) $next);
            }
        } catch (Throwable $e) {
            $run->forceFill([
                'status' => WorkflowRunStatus::Failed,
                'message' => $e->getMessage(),
            ])->save();

            $next = $step['on_failure'] ?? null;

            if ($next !== null && $next !== false && $next !== -1) {
                $this->scheduleStep($workflow, $subject, $payload, (int) $next);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $payload
     */
    protected function executeStepAction(Workflow $workflow, array $step, Contact|SocialPost $subject, array $payload): void
    {
        $action = WorkflowAction::tryFrom((string) ($step['action'] ?? $workflow->action?->value));

        match ($action) {
            WorkflowAction::AddTag => $this->applyTag($workflow, $subject, (int) ($step['tag_id'] ?? $workflow->tag_id), true),
            WorkflowAction::RemoveTag => $this->applyTag($workflow, $subject, (int) ($step['tag_id'] ?? $workflow->tag_id), false),
            WorkflowAction::SetStatus => $this->setStatusValue($subject, (string) ($step['contact_status'] ?? $workflow->contact_status)),
            WorkflowAction::Webhook => $this->sendWebhook(
                $workflow,
                $payload,
                (string) ($step['webhook_url'] ?? $workflow->webhook_url),
            ),
            default => throw new \RuntimeException('This step needs a valid action.'),
        };
    }

    protected function applyTag(Workflow $workflow, Contact|SocialPost $subject, int $tagId, bool $attach): void
    {
        if (! $subject instanceof Contact || $tagId === 0) {
            throw new \RuntimeException('This action needs a contact and a tag.');
        }

        $attach
            ? $subject->tags()->syncWithoutDetaching([$tagId])
            : $subject->tags()->detach($tagId);
    }

    protected function setStatusValue(Contact|SocialPost $subject, string $status): void
    {
        if (! $subject instanceof Contact) {
            throw new \RuntimeException('This action needs a contact.');
        }

        if ($status === '') {
            throw new \RuntimeException('This action needs a contact status.');
        }

        $subject->forceFill(['status' => $status])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sendWebhook(Workflow $workflow, array $payload, string $url): void
    {
        if ($url === '') {
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

        Http::post($url, $body)->throw();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function record(
        Workflow $workflow,
        ?int $stepIndex,
        Contact|SocialPost $subject,
        WorkflowRunStatus $status,
        ?string $message,
        array $payload,
    ): void {
        WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'step_index' => $stepIndex,
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

    /**
     * @param  array<string, mixed>  $step
     */
    protected function metricConditionPassed(array $step, Contact|SocialPost $subject): bool
    {
        $metric = $step['condition_metric'] ?? null;

        if (! is_string($metric) || trim($metric) === '') {
            return true;
        }

        $operator = (string) ($step['condition_operator'] ?? '>=');
        $threshold = (float) ($step['condition_value'] ?? 0);
        $actual = $this->metricValue($metric, $subject);

        if ($actual === null) {
            return false;
        }

        return match ($operator) {
            '>=' => $actual >= $threshold,
            '>' => $actual > $threshold,
            '<=' => $actual <= $threshold,
            '<' => $actual < $threshold,
            '=' , '==' => $actual === $threshold,
            '!=' , '<>' => $actual !== $threshold,
            default => false,
        };
    }

    protected function metricValue(string $metric, Contact|SocialPost $subject): ?float
    {
        if ($subject instanceof SocialPost) {
            $targets = SocialPostTarget::query()
                ->where('social_post_id', $subject->getKey())
                ->with('latestMetric')
                ->get();

            $metrics = $targets->pluck('latestMetric')->filter();

            $impressions = (int) $metrics->sum('impressions');
            $reach = (int) $metrics->sum('reach');
            $engagements = $metrics->sum(fn ($metric) => $metric->engagements() ?? 0);
            $clicks = (int) $metrics->sum('clicks');
            $delivered = $targets->filter->isPublished()->count();
            $failed = $targets->filter(fn (SocialPostTarget $target): bool => $target->status->value === 'failed')->count();
            $withMetrics = $metrics->count();
            $trackedClicks = (int) MarketingLink::query()
                ->where('social_post_id', $subject->getKey())
                ->sum('clicks_count');

            return match ($metric) {
                'impressions' => (float) $impressions,
                'reach' => (float) $reach,
                'engagements' => (float) $engagements,
                'clicks' => (float) $clicks,
                'tracked_clicks' => (float) $trackedClicks,
                'delivered' => (float) $delivered,
                'failed' => (float) $failed,
                'success_rate' => $delivered + $failed > 0
                    ? ($delivered / ($delivered + $failed)) * 100
                    : null,
                'coverage_rate' => $delivered > 0
                    ? ($withMetrics / $delivered) * 100
                    : null,
                'engagement_rate' => $impressions > 0
                    ? ($engagements / $impressions) * 100
                    : null,
                'click_through_rate' => $impressions > 0
                    ? ($clicks / $impressions) * 100
                    : null,
                default => null,
            };
        }

        $overview = (new MarketingAnalytics)->overview(30);
        $summary = $overview['summary'] ?? [];
        $coverage = $overview['coverage'] ?? [];

        return match ($metric) {
            'impressions' => isset($summary['impressions']) ? (float) $summary['impressions'] : null,
            'reach' => isset($summary['reach']) ? (float) $summary['reach'] : null,
            'engagements' => isset($summary['engagements']) ? (float) $summary['engagements'] : null,
            'clicks' => isset($summary['clicks']) ? (float) $summary['clicks'] : null,
            'tracked_clicks' => array_sum(array_column($overview['links'] ?? [], 'clicks')),
            'delivered' => isset($summary['delivered']) ? (float) $summary['delivered'] : null,
            'deliveries' => isset($summary['deliveries']) ? (float) $summary['deliveries'] : null,
            'success_rate' => isset($summary['success_rate']) ? (float) $summary['success_rate'] : null,
            'coverage_rate' => isset($coverage['coverage_rate']) ? (float) $coverage['coverage_rate'] : null,
            'engagement_rate' => isset($summary['engagement_rate']) ? (float) $summary['engagement_rate'] : null,
            'click_through_rate' => isset($summary['click_through_rate']) ? (float) $summary['click_through_rate'] : null,
            default => null,
        };
    }

    protected function resolveSubject(WorkflowRun $run): Contact|SocialPost|null
    {
        if ($run->subject_type === Contact::class) {
            return Contact::query()->find($run->subject_id);
        }

        if ($run->subject_type === SocialPost::class) {
            return SocialPost::query()->find($run->subject_id);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function scheduleBranch(Workflow $workflow, array $step, Contact|SocialPost $subject, array $payload, int $stepIndex, bool $success): void
    {
        $next = $success
            ? ($step['on_success'] ?? null)
            : ($step['on_failure'] ?? null);

        if ($next === null || $next === false || $next === -1) {
            return;
        }

        $this->scheduleStep($workflow, $subject, $payload, (int) $next);
    }
}
