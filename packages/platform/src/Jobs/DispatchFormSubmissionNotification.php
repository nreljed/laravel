<?php

namespace Nreljed\Platform\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Nreljed\Platform\Models\FormSubmission;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Notifications\AppNotification;
use Nreljed\Platform\Services\NotificationService;
use Nreljed\Platform\Support\FormRegistry;

/**
 * Fan out a form submission to every admin.
 *
 * Runs on the queue so a slow or failing notification never sits in front of
 * the visitor who just pressed Send. The submission is re-read inside the job,
 * so a form that is deleted between dispatch and run degrades to a no-op.
 */
class DispatchFormSubmissionNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        protected int $submissionId,
        protected string $formKey,
    ) {}

    public function handle(FormRegistry $registry): void
    {
        $submission = FormSubmission::find($this->submissionId);

        if ($submission === null) {
            return;
        }

        $definition = $registry->get($this->formKey);
        $type = is_array($definition) ? ($definition['notify'] ?? null) : null;

        if (! is_string($type) || $type === '') {
            return;
        }

        try {
            $notifications = app(NotificationService::class);
            $link = $this->linkFor($submission, $definition);
            $excerpt = $registry->excerpt($definition, (array) $submission->payload);

            User::query()
                ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))
                ->get()
                ->each(function (User $admin) use ($notifications, $submission, $type, $link, $definition, $excerpt) {
                    $notifications->notifyViaPreferences(
                        $admin,
                        $type,
                        new AppNotification($type, [
                            'title' => $this->title($submission, $definition),
                            'message' => Str::limit($excerpt ?? (string) $submission->summary, 200),
                            'link' => $link,
                            'severity' => 'info',
                            'module' => $this->module($definition),
                            'senderName' => $submission->name,
                            'senderEmail' => $submission->email,
                            'subjectLine' => $submission->summary,
                            'messageBody' => $excerpt ?? $submission->field('message', $submission->summary),
                            'submittedAt' => $submission->created_at?->toDateTimeString(),
                            'contactUrl' => $link,
                        ]),
                    );
                });
        } catch (\Throwable $e) {
            Log::error(
                '[FormSubmissions] Failed to notify admins about '.$this->formKey.' submission #'.$submission->id
                .' | Error: '.$e->getMessage()
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error(
            '[FormSubmissions] Notification job failed permanently for '.$this->formKey
            .' submission #'.$this->submissionId.' | Error: '.$exception->getMessage()
        );
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function title(FormSubmission $submission, array $definition): string
    {
        $label = (string) ($definition['label'] ?? $this->formKey);

        return 'New '.$label.' submission: '.($submission->summary ?: $submission->name ?: '#'.$submission->id);
    }

    /**
     * Point at the per-form admin detail page when the form owns one, otherwise
     * at the universal inbox detail page.
     *
     * @param  array<string, mixed>  $definition
     */
    protected function linkFor(FormSubmission $submission, array $definition): string
    {
        if (($definition['permission'] ?? '') === 'manage-contact' && Route::has('dashboard.admin.contact.show')) {
            return route('dashboard.admin.contact.show', $submission);
        }

        if (Route::has('dashboard.admin.submissions.show')) {
            return route('dashboard.admin.submissions.show', $submission);
        }

        return url('/');
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    protected function module(array $definition): string
    {
        return (string) ($definition['module'] ?? 'core');
    }
}
