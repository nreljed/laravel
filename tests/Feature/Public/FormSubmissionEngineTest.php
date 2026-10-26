<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nreljed\Platform\Models\FormSubmission;
use Nreljed\Platform\Support\FormRegistry;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

/**
 * Integration coverage for the generic form-submission engine.
 *
 * The static contract lives in the platform package's FormSubmissionsContractTest;
 * what is pinned here is the behaviour that only shows up against a real
 * application — route wiring, authorization, the CSV download and the bulk
 * actions.
 */
class FormSubmissionEngineTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_any_declared_form_is_collectable_at_the_generic_endpoint(): void
    {
        $this->registerFeedbackForm();

        $response = $this->post(route('forms.store', 'feedback'), [
            'title' => 'The sidebar is hard to scan',
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'body' => 'Long enough to clear the minimum length rule.',
        ]);

        $response->assertSessionHas('status', 'feedback-sent');

        $submission = FormSubmission::firstOrFail();

        $this->assertSame('feedback', $submission->form_key);
        $this->assertSame('The sidebar is hard to scan', $submission->summary);
        $this->assertSame('The sidebar is hard to scan', $submission->field('title'));
    }

    public function test_collecting_an_undeclared_form_is_a_404(): void
    {
        $this->post(route('forms.store', 'not-a-real-form'), [
            'title' => 'Anything',
        ])->assertNotFound();

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_the_universal_inbox_requires_the_manage_submissions_permission(): void
    {
        $this->actingAs($this->editor())
            ->get(route('dashboard.admin.submissions.index'))
            ->assertForbidden();
    }

    public function test_the_universal_inbox_lists_submissions_from_every_form(): void
    {
        $this->registerFeedbackForm();

        FormSubmission::create([
            'form_key' => 'contact',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'summary' => 'Billing question',
            'payload' => [],
        ]);

        FormSubmission::create([
            'form_key' => 'feedback',
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'summary' => 'Sidebar is hard to scan',
            'payload' => [],
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/submissions/index')
            ->where('submissions.total', 2)
            ->has('submissions.data', 2)
            ->where('pinnedForm', null)
            ->has('forms', 2),
        );
    }

    public function test_the_pinned_contact_inbox_only_shows_contact_rows(): void
    {
        $this->registerFeedbackForm();

        FormSubmission::create(['form_key' => 'contact', 'name' => 'A', 'email' => 'a@example.com', 'payload' => []]);
        FormSubmission::create(['form_key' => 'feedback', 'name' => 'B', 'email' => 'b@example.com', 'payload' => []]);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.contact.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/submissions/index')
            ->where('submissions.total', 1)
            ->where('pinnedForm', 'contact'),
        );
    }

    public function test_the_export_downloads_a_csv_that_is_guarded_against_formula_injection(): void
    {
        FormSubmission::create([
            'form_key' => 'contact',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'summary' => '=HYPERLINK("http://evil.example")',
            'payload' => [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'subject' => '=HYPERLINK("http://evil.example")',
                'message' => '+1234567890 is my number',
            ],
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
        $response->assertDownload();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('ID,Form,Status,Name,Email,Subject,Message', $csv);
        $this->assertSame(1, substr_count($csv, 'Name'), 'Identity columns must not be duplicated in the export.');
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+1234567890", $csv);
        $this->assertStringContainsString('Ada Lovelace', $csv);
    }

    public function test_the_export_is_audited(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('dashboard.admin.submissions.export'))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'data_exported',
            'user_id' => $admin->id,
        ]);
    }

    public function test_bulk_actions_update_many_submissions_at_once(): void
    {
        $ids = collect(range(1, 3))->map(fn (int $i) => FormSubmission::create([
            'form_key' => 'contact',
            'name' => "Sender {$i}",
            'email' => "sender{$i}@example.com",
            'payload' => [],
        ])->id);

        $this->actingAs($this->admin())
            ->post(route('dashboard.admin.submissions.bulk'), [
                'action' => 'archive',
                'ids' => $ids->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(3, FormSubmission::where('status', FormSubmission::STATUS_ARCHIVED)->count());
    }

    public function test_bulk_delete_removes_rows_and_is_audited(): void
    {
        $admin = $this->admin();

        $ids = collect(range(1, 2))->map(fn (int $i) => FormSubmission::create([
            'form_key' => 'contact',
            'name' => "Sender {$i}",
            'email' => "sender{$i}@example.com",
            'payload' => [],
        ])->id);

        $this->actingAs($admin)
            ->post(route('dashboard.admin.submissions.bulk'), [
                'action' => 'delete',
                'ids' => $ids->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('form_submissions', 0);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'form_submission_deleted',
            'user_id' => $admin->id,
        ]);
    }

    public function test_bulk_rejects_an_unknown_action(): void
    {
        $this->actingAs($this->admin())
            ->post(route('dashboard.admin.submissions.bulk'), [
                'action' => 'nuke_everything',
                'ids' => [1],
            ])
            ->assertSessionHasErrors('action');
    }

    public function test_the_inbox_preview_shows_the_message_body_not_the_summary_again(): void
    {
        FormSubmission::create([
            'form_key' => 'contact',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'summary' => 'Billing question',
            'payload' => [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'subject' => 'Billing question',
                'message' => 'How do I export last quarter costs?',
            ],
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('submissions.data.0.summary', 'Billing question')
            ->where('submissions.data.0.excerpt', 'How do I export last quarter costs?'),
        );
    }

    public function test_the_detail_page_renders_the_payload_in_declared_field_order(): void
    {
        $this->registerFeedbackForm();

        $submission = FormSubmission::create([
            'form_key' => 'feedback',
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'summary' => 'Sidebar is hard to scan',
            'payload' => ['title' => 'Sidebar is hard to scan', 'body' => 'Needs more contrast.'],
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.show', $submission));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('dashboard/admin/submissions/show')
            ->where('submission.form_key', 'feedback')
            ->where('definition.label', 'Feedback')
            ->where('definition.fields.0.name', 'title')
            ->where('definition.fields.3.name', 'body'),
        );
    }

    public function test_the_inbox_filters_by_status(): void
    {
        $read = $this->submission(['status' => FormSubmission::STATUS_READ, 'read_at' => now()]);
        $this->submission();

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.index', ['status' => 'read']))
            ->assertInertia(fn ($page) => $page
                ->where('submissions.total', 1)
                ->where('submissions.data.0.id', $read->id),
            );
    }

    public function test_the_inbox_search_covers_the_json_payload(): void
    {
        $this->submission();

        $needle = $this->submission([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'summary' => 'Totals',
            'payload' => [
                'name' => 'Grace Hopper',
                'email' => 'grace@example.com',
                'subject' => 'Totals',
                'message' => 'The totals look off after midnight.',
            ],
        ]);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.index', ['search' => 'midnight']))
            ->assertInertia(fn ($page) => $page
                ->where('submissions.total', 1)
                ->where('submissions.data.0.id', $needle->id),
            );
    }

    public function test_the_inbox_filters_by_date_range(): void
    {
        $old = $this->submission();
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $recent = $this->submission(['summary' => 'Second']);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.index', [
                'date_from' => now()->subDays(2)->toDateString(),
                'date_to' => now()->toDateString(),
            ]))
            ->assertInertia(fn ($page) => $page
                ->where('submissions.total', 1)
                ->where('submissions.data.0.id', $recent->id),
            );
    }

    public function test_the_inbox_falls_back_to_a_safe_sort_for_unknown_input(): void
    {
        $older = $this->submission();
        $older->forceFill(['created_at' => now()->subDay()])->save();

        $newer = $this->submission(['summary' => 'Newest']);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.index', [
                'sort' => 'payload; drop table form_submissions',
                'direction' => 'sideways',
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('submissions.data.0.id', $newer->id)
                ->where('submissions.data.1.id', $older->id),
            );
    }

    public function test_the_inbox_clamps_the_page_size_to_a_supported_value(): void
    {
        $this->submission();

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.index', ['per_page' => 5000]))
            ->assertInertia(fn ($page) => $page->where('submissions.per_page', 20));
    }

    public function test_the_export_only_contains_rows_matching_the_active_filters(): void
    {
        $this->submission();

        $this->submission([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'summary' => 'Feature request',
            'payload' => [
                'name' => 'Grace Hopper',
                'email' => 'grace@example.com',
                'subject' => 'Feature request',
                'message' => 'Could we export to XLSX?',
            ],
        ]);

        $csv = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.submissions.export', ['search' => 'Ada']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Ada Lovelace', $csv);
        $this->assertStringNotContainsString('Grace Hopper', $csv);
    }

    public function test_bulk_mark_read_marks_every_selected_submission_read(): void
    {
        $first = $this->submission();
        $second = $this->submission(['summary' => 'Second']);

        $this->actingAs($this->admin())
            ->post(route('dashboard.admin.submissions.bulk'), [
                'action' => 'mark_read',
                'ids' => [$first->id, $second->id],
            ])
            ->assertRedirect();

        foreach ([$first, $second] as $submission) {
            $fresh = $submission->fresh();

            $this->assertSame(FormSubmission::STATUS_READ, $fresh->status);
            $this->assertNotNull($fresh->read_at);
        }
    }

    /**
     * A stored contact submission with a coherent identity + payload pair.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function submission(array $attributes = []): FormSubmission
    {
        return FormSubmission::create(array_merge([
            'form_key' => 'contact',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'summary' => 'Billing question',
            'payload' => [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'subject' => 'Billing question',
                'message' => 'How do I export last quarter costs?',
            ],
        ], $attributes));
    }

    /**
     * Contribute a second form at runtime, exactly as a feature provider's
     * boot() would.
     */
    protected function registerFeedbackForm(): void
    {
        app(FormRegistry::class)->register('feedback', [
            'label' => 'Feedback',
            'description' => 'In-product feedback.',
            'permission' => 'manage-submissions',
            'redirect' => 'home',
            'identity' => ['name' => 'name', 'email' => 'email', 'summary' => 'title'],
            'fields' => [
                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'max' => 190],
                ['name' => 'body', 'label' => 'Details', 'type' => 'textarea', 'required' => true, 'min' => 10, 'max' => 5000],
            ],
            'rate_limit' => '10,1',
            'dedupe_window' => 0,
        ]);
    }
}
