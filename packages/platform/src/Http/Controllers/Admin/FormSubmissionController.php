<?php

namespace Nreljed\Platform\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Enums\AuditAction;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\FormSubmission;
use Nreljed\Platform\Services\AuditService;
use Nreljed\Platform\Support\CsvExporter;
use Nreljed\Platform\Support\FormRegistry;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Universal admin inbox for every declared form.
 *
 * The same controller serves the pinned per-form routes (the contact group
 * keeps working exactly as before) and the universal inbox that shows all
 * forms at once. Authorization is enforced by route middleware: `manage-contact`
 * for the contact view, `manage-submissions` for the cross-form inbox.
 */
class FormSubmissionController extends Controller
{
    public function __construct(protected FormRegistry $registry) {}

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $stats = $this->stats($filters['form']);

        $submissions = $this->query($request, $filters)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('dashboard/admin/submissions/index', [
            'submissions' => $submissions->through(function (FormSubmission $submission): array {
                $definition = $this->registry->get($submission->form_key) ?? [];

                return [
                    'id' => $submission->id,
                    'form_key' => $submission->form_key,
                    'form_label' => $definition['label'] ?? $submission->form_key,
                    'name' => $submission->name,
                    'email' => $submission->email,
                    'summary' => $submission->summary,
                    'excerpt' => Str::limit((string) $this->registry->excerpt($definition, $submission->payload ?? []), 120),
                    'status' => $submission->status,
                    'read_at' => $submission->read_at?->toISOString(),
                    'created_at' => $submission->created_at?->toISOString(),
                ];
            }),
            'stats' => $stats,
            'filters' => $filters,
            'statuses' => FormSubmission::statuses(),
            'forms' => $this->formOptions(),
            'pinnedForm' => $this->pinnedFormKey($request),
            'definitions' => $this->registry->all(),
            'perPages' => [10, 25, 50, 100],
        ]);
    }

    public function show(Request $request, FormSubmission $submission): Response
    {
        $submission->markAsRead();

        $definition = $this->registry->get($submission->form_key);

        return Inertia::render('dashboard/admin/submissions/show', [
            'submission' => [
                'id' => $submission->id,
                'form_key' => $submission->form_key,
                'form_label' => $definition['label'] ?? $submission->form_key,
                'name' => $submission->name,
                'email' => $submission->email,
                'summary' => $submission->summary,
                'payload' => $submission->payload,
                'status' => $submission->status,
                'ip' => $submission->ip,
                'user_agent' => $submission->user_agent,
                'referrer' => $submission->referrer,
                'utm' => [
                    'source' => $submission->utm_source,
                    'medium' => $submission->utm_medium,
                    'campaign' => $submission->utm_campaign,
                    'term' => $submission->utm_term,
                    'content' => $submission->utm_content,
                ],
                'consent_at' => $submission->consent_at?->toISOString(),
                'read_at' => $submission->read_at?->toISOString(),
                'created_at' => $submission->created_at?->toISOString(),
            ],
            'definition' => $definition,
            'statuses' => FormSubmission::statuses(),
            'pinnedForm' => $this->pinnedFormKey($request),
        ]);
    }

    public function update(Request $request, FormSubmission $submission): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', FormSubmission::statuses())],
        ]);

        $status = $validated['status'];

        $submission->forceFill([
            'status' => $status,
            'read_at' => $status === FormSubmission::STATUS_NEW
                ? null
                : ($submission->read_at ?? now()),
        ])->save();

        return back()->with('status', 'Submission updated.');
    }

    public function destroy(Request $request, FormSubmission $submission): RedirectResponse
    {
        $submission->delete();

        AuditService::log(
            AuditAction::FormSubmissionDeleted,
            (int) $request->user()?->id,
            ['module' => 'forms', 'form' => $submission->form_key, 'id' => $submission->id],
        );

        return redirect()
            ->route($this->indexRoute($request))
            ->with('status', 'Submission deleted.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:mark_read,archive,delete'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $query = FormSubmission::whereIn('id', $validated['ids']);

        $filters = $this->filters($request);

        if ($filters['form'] !== null) {
            $query->where('form_key', $filters['form']);
        }

        $count = 0;

        match ($validated['action']) {
            'mark_read' => $count = $query->where('status', FormSubmission::STATUS_NEW)
                ->update(['status' => FormSubmission::STATUS_READ, 'read_at' => now()]),
            'archive' => $count = $query->where('status', '!=', FormSubmission::STATUS_ARCHIVED)
                ->update(['status' => FormSubmission::STATUS_ARCHIVED, 'read_at' => now()]),
            'delete' => $count = $query->delete(),
            default => null,
        };

        if ($validated['action'] === 'delete') {
            AuditService::log(
                AuditAction::FormSubmissionDeleted,
                (int) $request->user()?->id,
                ['module' => 'forms', 'event' => 'bulk_delete', 'form' => $filters['form'], 'count' => $count, 'ids' => $validated['ids']],
            );
        }

        return back()->with('status', $count.' submission(s) updated.');
    }

    /**
     * Filter-aware CSV export. Columns are identity → the form's declared
     * field order → metadata, so a spreadsheet consumer's column positions
     * stay stable when a form gains a field at the end.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $formKey = $filters['form'];

        $definitions = $formKey !== null
            ? [$formKey => $this->registry->get($formKey)]
            : $this->registry->all();

        $columns = $this->exportColumns($definitions);

        AuditService::log(
            AuditAction::DataExported,
            (int) $request->user()?->id,
            ['module' => 'forms', 'form' => $formKey, 'filters' => array_filter($filters)],
        );

        $filename = 'form-submissions'.($formKey !== null ? '-'.$formKey : '').'-'.now()->format('Y-m-d-His').'.csv';

        return app(CsvExporter::class)->stream($filename, function (CsvExporter $csv) use ($request, $filters, $columns) {
            $csv->writeLine($columns['headers']);

            $this->exportQuery($request, $filters)
                ->lazyById(200, 'id')
                ->each(function (FormSubmission $submission) use ($csv, $columns) {
                    $csv->writeLine($columns['row']($submission));
                });
        });
    }

    /**
     * Column plan for the export: the union of every form in scope, in
     * registry order, so a single mixed export still has a stable header.
     *
     * @param  array<string, array<string, mixed>|null>  $definitions
     * @return array{headers: array<int, string>, row: callable}
     */
    protected function exportColumns(array $definitions): array
    {
        $payloadColumns = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)) {
                continue;
            }

            foreach ($this->registry->fields($definition) as $field) {
                $name = $field['name'] ?? null;

                if (is_string($name) && $name !== '' && ! isset($payloadColumns[$name])) {
                    $payloadColumns[$name] = (string) ($field['label'] ?? $name);
                }
            }
        }

        $headers = array_merge([
            'ID',
            'Form',
            'Status',
        ], array_values($payloadColumns), [
            'IP Address',
            'User Agent',
            'Referrer',
            'UTM Source',
            'UTM Medium',
            'UTM Campaign',
            'UTM Term',
            'UTM Content',
            'Consent At',
            'Read At',
            'Received At',
        ]);

        $payloadKeys = array_keys($payloadColumns);

        $row = function (FormSubmission $submission) use ($payloadKeys) {
            $payload = (array) $submission->payload;

            return array_merge([
                $submission->id,
                $this->registry->get($submission->form_key)['label'] ?? $submission->form_key,
                $submission->status,
            ], array_map(fn (string $key) => $payload[$key] ?? '', $payloadKeys), [
                $submission->ip,
                $submission->user_agent,
                $submission->referrer,
                $submission->utm_source,
                $submission->utm_medium,
                $submission->utm_campaign,
                $submission->utm_term,
                $submission->utm_content,
                $submission->consent_at?->toDateTimeString(),
                $submission->read_at?->toDateTimeString(),
                $submission->created_at?->toDateTimeString(),
            ]);
        };

        return ['headers' => $headers, 'row' => $row];
    }

    /**
     * @return array{form: ?string, status: ?string, search: ?string, date_from: ?string, date_to: ?string, sort: string, direction: string, per_page: int}
     */
    protected function filters(Request $request): array
    {
        $pinned = $this->pinnedFormKey($request);
        $queryForm = $request->query('form');
        $form = $pinned ?? (is_string($queryForm) && $queryForm !== '' && $queryForm !== 'all' ? $queryForm : null);

        if ($form !== null && ! isset($this->registry->all()[$form])) {
            $form = $pinned;
        }

        $perPage = (int) $request->query('per_page', 20);

        return [
            'form' => $form,
            'status' => is_string($request->query('status')) ? $request->query('status') : null,
            'search' => is_string($request->query('search')) ? trim($request->query('search')) : null,
            'date_from' => is_string($request->query('date_from')) ? $request->query('date_from') : null,
            'date_to' => is_string($request->query('date_to')) ? $request->query('date_to') : null,
            'sort' => is_string($request->query('sort')) ? $request->query('sort') : 'created_at',
            'direction' => is_string($request->query('direction')) ? $request->query('direction') : 'desc',
            'per_page' => in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 20,
        ];
    }

    /**
     * @param  array{form: ?string, status: ?string, search: ?string, date_from: ?string, date_to: ?string, sort: string, direction: string, per_page: int}  $filters
     */
    protected function query(Request $request, array $filters)
    {
        return FormSubmission::query()
            ->formKey($filters['form'])
            ->status($filters['status'])
            ->search($filters['search'])
            ->betweenDates($filters['date_from'], $filters['date_to'])
            ->ordered($filters['sort'], $filters['direction']);
    }

    /**
     * @return array{total: int, new: int, read: int, archived: int}
     */
    protected function stats(?string $formKey): array
    {
        $grouped = FormSubmission::query()
            ->formKey($formKey)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $grouped->sum(),
            'new' => (int) $grouped->get(FormSubmission::STATUS_NEW, 0),
            'read' => (int) $grouped->get(FormSubmission::STATUS_READ, 0),
            'archived' => (int) $grouped->get(FormSubmission::STATUS_ARCHIVED, 0),
        ];
    }

    /**
     * Form filter options with live counts across every form.
     *
     * @return array<int, array{key: string, label: string, count: int}>
     */
    protected function formOptions(): array
    {
        $counts = FormSubmission::query()
            ->selectRaw('form_key, count(*) as total')
            ->groupBy('form_key')
            ->pluck('total', 'form_key');

        $options = [];

        foreach ($this->registry->all() as $key => $definition) {
            $options[] = [
                'key' => $key,
                'label' => (string) ($definition['label'] ?? $key),
                'count' => (int) $counts->get($key, 0),
            ];
        }

        return $options;
    }

    /**
     * The form the route is pinned to (`/admin/contact/{form?}` style routes),
     * or null when this is the universal cross-form inbox.
     */
    protected function pinnedFormKey(Request $request): ?string
    {
        $key = $request->route('form_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Export applies the same filters as the screen but always walks id order,
     * so a download is deterministic and does not hold a sort open mid-stream.
     *
     * @param  array{form: ?string, status: ?string, search: ?string, date_from: ?string, date_to: ?string}  $filters
     */
    protected function exportQuery(Request $request, array $filters)
    {
        return FormSubmission::query()
            ->formKey($filters['form'])
            ->status($filters['status'])
            ->search($filters['search'])
            ->betweenDates($filters['date_from'], $filters['date_to']);
    }

    protected function indexRoute(Request $request): string
    {
        return $this->pinnedFormKey($request) !== null
            ? 'dashboard.admin.contact.index'
            : 'dashboard.admin.submissions.index';
    }
}
