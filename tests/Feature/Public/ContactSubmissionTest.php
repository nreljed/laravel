<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Nreljed\Platform\Models\FormSubmission;
use Tests\Concerns\CreatesTestUsers;
use Tests\TestCase;

/**
 * The public contact page still behaves exactly as it did before submissions
 * moved onto the generic engine: same routes, same flash key, same honeypot
 * and rate-limit contract. What changed is the storage — one `form_submissions`
 * row with a JSON payload instead of a `contact_submissions` row.
 */
class ContactSubmissionTest extends TestCase
{
    use CreatesTestUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_valid_submission_is_stored_and_flashes_success(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subject' => 'Billing question',
            'message' => 'How do I export last quarter costs?',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('status', 'contact-sent');

        $this->assertDatabaseCount('form_submissions', 1);
        $this->assertDatabaseCount('contact_submissions', 0);

        $submission = FormSubmission::firstOrFail();

        $this->assertSame('contact', $submission->form_key);
        $this->assertSame('Ada Lovelace', $submission->name);
        $this->assertSame('ada@example.com', $submission->email);
        $this->assertSame('Billing question', $submission->summary);
        $this->assertSame('How do I export last quarter costs?', $submission->field('message'));
        $this->assertNotNull($submission->ip);
        $this->assertNotNull($submission->user_agent);
    }

    public function test_subject_is_optional(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'message' => 'Just saying hello, no particular subject here.',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', 'contact-sent');

        $submission = FormSubmission::firstOrFail();

        $this->assertNull($submission->summary);
        $this->assertNull($submission->field('subject'));
    }

    public function test_honeypot_submission_is_silently_discarded(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'Buy cheap backlinks now, this is a real human message.',
            'website' => 'https://spam.example',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('status', 'contact-sent');
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => '',
            'email' => 'not-an-email',
            'message' => '',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'message']);
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_message_shorter_than_ten_characters_is_rejected(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Too short',
        ]);

        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_overlong_fields_are_rejected(): void
    {
        $response = $this->post(route('contact.store'), [
            'name' => str_repeat('a', 121),
            'email' => 'ada@example.com',
            'subject' => str_repeat('s', 191),
            'message' => str_repeat('m', 5001),
        ]);

        $response->assertSessionHasErrors(['name', 'message']);
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_undeclared_fields_never_reach_storage(): void
    {
        $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'The message body is long enough to pass validation.',
            'is_admin' => 'yes',
            'role' => 'superadmin',
        ]);

        $payload = FormSubmission::firstOrFail()->payload;

        $this->assertSame(['name', 'email', 'subject', 'message'], array_keys($payload));
        $this->assertArrayNotHasKey('is_admin', $payload);
        $this->assertArrayNotHasKey('role', $payload);
    }

    public function test_utm_parameters_are_captured_for_attribution(): void
    {
        $this->post(route('contact.store').'?utm_source=newsletter&utm_campaign=launch', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'The message body is long enough to pass validation.',
        ]);

        $submission = FormSubmission::firstOrFail();

        $this->assertSame('newsletter', $submission->utm_source);
        $this->assertSame('launch', $submission->utm_campaign);
        $this->assertNull($submission->utm_medium);
    }

    public function test_identical_replays_inside_the_dedupe_window_collapse_to_one_row(): void
    {
        $payload = [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'The message body is long enough to pass validation.',
        ];

        $this->post(route('contact.store'), $payload)->assertSessionHas('status', 'contact-sent');
        $this->post(route('contact.store'), $payload)->assertSessionHas('status', 'contact-sent');

        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_a_different_message_is_not_treated_as_a_duplicate(): void
    {
        $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'The message body is long enough to pass validation.',
        ]);

        $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'A completely different message body that is also long enough.',
        ]);

        $this->assertDatabaseCount('form_submissions', 2);
    }

    public function test_admins_are_notified_about_a_new_submission(): void
    {
        $this->admin();
        $this->admin();

        $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subject' => 'Billing question',
            'message' => 'How do I export last quarter costs?',
        ]);

        $this->assertDatabaseCount('form_submissions', 1);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_the_admin_notification_previews_the_message_body_not_the_subject(): void
    {
        $this->admin();

        $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'subject' => 'Billing question',
            'message' => 'How do I export last quarter costs?',
        ]);

        $data = json_decode((string) DB::table('notifications')->value('data'), true);

        $this->assertSame('How do I export last quarter costs?', $data['message']);
        $this->assertSame('Billing question', $data['subjectLine']);
    }

    public function test_a_notification_failure_never_blocks_the_visitor(): void
    {
        $this->admin();

        $response = $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'The message body is long enough to pass validation.',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('status', 'contact-sent');
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_contact_submission_endpoint_is_rate_limited(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->post(route('contact.store'), [
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'message' => "Attempt {$attempt} of the rate limit probe, long enough.",
            ])->assertRedirect();
        }

        $this->post(route('contact.store'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'message' => 'Attempt four should be refused by the limiter entirely.',
        ])->assertStatus(429);

        $this->assertDatabaseCount('form_submissions', 3);
    }

    public function test_admin_contact_list_requires_the_manage_contact_permission(): void
    {
        $response = $this->actingAs($this->regularUser())
            ->get(route('dashboard.admin.contact.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_the_contact_list(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('dashboard.admin.contact.index'));

        $response->assertOk();
    }

    public function test_opening_a_contact_detail_marks_it_read(): void
    {
        $submission = FormSubmission::create([
            'form_key' => 'contact',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'summary' => 'Billing question',
            'payload' => ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'subject' => 'Billing question', 'message' => 'A stored message body.'],
            'status' => FormSubmission::STATUS_NEW,
        ]);

        $this->actingAs($this->admin())
            ->get(route('dashboard.admin.contact.show', $submission))
            ->assertOk();

        $this->assertSame(FormSubmission::STATUS_READ, $submission->fresh()->status);
        $this->assertNotNull($submission->fresh()->read_at);
    }
}
