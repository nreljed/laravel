<?php

namespace Nreljed\Platform\PublicSite\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Http\Requests\ContactRequest;
use Nreljed\Platform\Models\ContactSubmission;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Notifications\AppNotification;
use Nreljed\Platform\Services\NotificationService;

class PublicController extends Controller
{
    public function about()
    {
        return Inertia::render('public/about');
    }

    public function services()
    {
        return Inertia::render('public/services');
    }

    public function contact()
    {
        return Inertia::render('public/contact');
    }

    /**
     * Store a public contact form submission and notify every admin.
     *
     * The response is always a redirect back to the contact page with the same
     * flash key whether the submission was stored or discarded by the honeypot,
     * so a bot cannot distinguish success from rejection.
     */
    public function submit(ContactRequest $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return $this->sentResponse();
        }

        $submission = ContactSubmission::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'subject' => $request->filled('subject') ? $request->string('subject')->toString() : null,
            'message' => $request->string('message')->toString(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
        ]);

        $this->notifyAdmins($submission);

        return $this->sentResponse();
    }

    protected function sentResponse(): RedirectResponse
    {
        return redirect()
            ->route('contact')
            ->with('status', 'contact-sent');
    }

    /**
     * Emit the `contact.received` type to every admin, in-app and by email.
     *
     * The type is required, so it always delivers. Failures are swallowed so a
     * notification problem never surfaces to the visitor who just submitted.
     */
    protected function notifyAdmins(ContactSubmission $submission): void
    {
        try {
            $notifications = app(NotificationService::class);

            $contactUrl = route('dashboard.admin.contact.show', $submission);

            User::query()
                ->whereHas('roles', fn ($query) => $query->where('slug', 'admin'))
                ->get()
                ->each(function (User $admin) use ($notifications, $submission, $contactUrl) {
                    $notifications->notifyViaPreferences(
                        $admin,
                        'contact.received',
                        new AppNotification('contact.received', [
                            'title' => 'New contact message: '.($submission->subject ?: $submission->name),
                            'message' => Str::limit($submission->message, 200),
                            'link' => $contactUrl,
                            'severity' => 'info',
                            'module' => 'core',
                            'senderName' => $submission->name,
                            'senderEmail' => $submission->email,
                            'subjectLine' => $submission->subject,
                            'messageBody' => $submission->message,
                            'submittedAt' => $submission->created_at?->toDateTimeString(),
                            'contactUrl' => $contactUrl,
                        ]),
                    );
                });
        } catch (\Throwable $e) {
            Log::error('[Contact] Failed to notify admins about submission #'.$submission->id.' | Error: '.$e->getMessage());
        }
    }
}
