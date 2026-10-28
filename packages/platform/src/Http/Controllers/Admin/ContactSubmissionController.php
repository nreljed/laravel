<?php

namespace Nreljed\Platform\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\ContactSubmission;

/**
 * Admin triage for public contact form submissions.
 *
 * Authorization is enforced by the `permission:manage-contact` route middleware;
 * the controller assumes the caller already holds it.
 */
class ContactSubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $search = $request->query('search');

        $query = ContactSubmission::query()
            ->status(is_string($status) ? $status : null);

        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('message', 'like', $like);
            });
        }

        $submissions = $query->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('dashboard/admin/contact/index', [
            'submissions' => $submissions->through(fn (ContactSubmission $submission) => [
                'id' => $submission->id,
                'name' => $submission->name,
                'email' => $submission->email,
                'subject' => $submission->subject,
                'excerpt' => Str::limit($submission->message, 120),
                'status' => $submission->status,
                'read_at' => $submission->read_at?->toISOString(),
                'created_at' => $submission->created_at?->toISOString(),
            ]),
            'stats' => [
                'total' => ContactSubmission::count(),
                'new' => ContactSubmission::where('status', ContactSubmission::STATUS_NEW)->count(),
                'read' => ContactSubmission::where('status', ContactSubmission::STATUS_READ)->count(),
                'archived' => ContactSubmission::where('status', ContactSubmission::STATUS_ARCHIVED)->count(),
            ],
            'filters' => [
                'status' => is_string($status) ? $status : null,
                'search' => is_string($search) ? $search : null,
            ],
            'statuses' => ContactSubmission::statuses(),
        ]);
    }

    public function show(ContactSubmission $submission): Response
    {
        $submission->markAsRead();

        return Inertia::render('dashboard/admin/contact/show', [
            'submission' => [
                'id' => $submission->id,
                'name' => $submission->name,
                'email' => $submission->email,
                'subject' => $submission->subject,
                'message' => $submission->message,
                'status' => $submission->status,
                'ip' => $submission->ip,
                'user_agent' => $submission->user_agent,
                'read_at' => $submission->read_at?->toISOString(),
                'created_at' => $submission->created_at?->toISOString(),
            ],
            'statuses' => ContactSubmission::statuses(),
        ]);
    }

    public function update(Request $request, ContactSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ContactSubmission::statuses())],
        ]);

        $status = $validated['status'];

        $submission->forceFill([
            'status' => $status,
            'read_at' => $status === ContactSubmission::STATUS_NEW
                ? null
                : ($submission->read_at ?? now()),
        ])->save();

        return back()->with('status', 'Contact message updated.');
    }

    public function destroy(ContactSubmission $submission): RedirectResponse
    {
        $submission->delete();

        return redirect()
            ->route('dashboard.admin.contact.index')
            ->with('status', 'Contact message deleted.');
    }
}
