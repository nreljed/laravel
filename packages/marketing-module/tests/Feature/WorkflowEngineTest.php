<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Enums\ContactStatus;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\Tag;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Models\WorkflowRun;
use Nreljed\MarketingModule\Services\WorkflowEngine;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The automation runtime: matching, conditions, actions, webhooks and the run
 * log that describes each outcome.
 */
final class WorkflowEngineTest extends TestCase
{
    use BootsDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
    }

    public function test_it_adds_a_tag_when_a_contact_is_created(): void
    {
        $contact = Contact::create(['email' => 'new@example.test', 'status' => ContactStatus::Lead]);
        $tag = Tag::create(['name' => 'New lead', 'slug' => 'new-lead']);
        $workflow = $this->workflow(WorkflowAction::AddTag, ['tag_id' => $tag->id]);

        (new WorkflowEngine)->contactCreated($contact);

        $this->assertTrue($contact->fresh()->tags->contains('id', $tag->id));
        $this->assertSame(1, $workflow->fresh()->run_count);
        $this->assertSame(1, WorkflowRun::query()->count());
        $this->assertSame(WorkflowRunStatus::Success, WorkflowRun::query()->first()->status);
    }

    public function test_it_removes_a_tag_from_a_contact(): void
    {
        $contact = Contact::create(['email' => 'old@example.test']);
        $tag = Tag::create(['name' => 'Customer', 'slug' => 'customer']);
        $contact->tags()->attach($tag);
        $workflow = $this->workflow(WorkflowAction::RemoveTag, ['tag_id' => $tag->id]);

        (new WorkflowEngine)->contactCreated($contact);

        $this->assertFalse($contact->fresh()->tags->contains('id', $tag->id));
        $this->assertSame(1, $workflow->fresh()->run_count);
    }

    public function test_it_sets_the_contact_status(): void
    {
        $contact = Contact::create(['email' => 'person@example.test', 'status' => ContactStatus::Lead]);
        $this->workflow(WorkflowAction::SetStatus, ['contact_status' => ContactStatus::Active->value]);

        (new WorkflowEngine)->contactCreated($contact);

        $this->assertSame(ContactStatus::Active, $contact->fresh()->status);
    }

    public function test_a_condition_tag_must_already_be_on_the_contact(): void
    {
        $condition = Tag::create(['name' => 'VIP', 'slug' => 'vip']);
        $action = Tag::create(['name' => 'Priority', 'slug' => 'priority']);
        $contact = Contact::create(['email' => 'not-vip@example.test']);
        $workflow = $this->workflow(WorkflowAction::AddTag, [
            'tag_id' => $action->id,
            'condition_tag_id' => $condition->id,
        ]);

        (new WorkflowEngine)->contactCreated($contact);

        $this->assertFalse($contact->fresh()->tags->contains('id', $action->id));
        $this->assertSame(0, $workflow->fresh()->run_count);
        $this->assertSame(0, WorkflowRun::query()->count());
    }

    public function test_a_matching_condition_lets_the_action_run(): void
    {
        $condition = Tag::create(['name' => 'VIP', 'slug' => 'vip']);
        $action = Tag::create(['name' => 'Priority', 'slug' => 'priority']);
        $contact = Contact::create(['email' => 'vip@example.test']);
        $contact->tags()->attach($condition);
        $this->workflow(WorkflowAction::AddTag, [
            'tag_id' => $action->id,
            'condition_tag_id' => $condition->id,
        ]);

        (new WorkflowEngine)->contactCreated($contact);

        $this->assertTrue($contact->fresh()->tags->contains('id', $action->id));
    }

    public function test_it_posts_the_event_payload_to_a_webhook(): void
    {
        $contact = Contact::create(['email' => 'hook@example.test']);
        $payloads = [];

        $engine = new WorkflowEngine(function (array $payload) use (&$payloads): void {
            $payloads[] = $payload;
        });

        $workflow = $this->workflow(WorkflowAction::Webhook, [
            'webhook_url' => 'https://example.test/hook',
        ]);

        $engine->contactCreated($contact);

        $this->assertCount(1, $payloads);
        $this->assertSame('contact_created', $payloads[0]['event']);
        $this->assertSame($workflow->id, $payloads[0]['workflow']['id']);
        $this->assertSame('hook@example.test', $payloads[0]['contact']['email']);
    }

    public function test_a_published_post_can_trigger_a_webhook(): void
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);

        $post = SocialPost::create([
            'user_id' => 1,
            'body' => 'Launch day',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $payloads = [];
        $engine = new WorkflowEngine(function (array $payload) use (&$payloads): void {
            $payloads[] = $payload;
        });

        $this->workflow(WorkflowAction::Webhook, [
            'trigger' => WorkflowTrigger::PostPublished,
            'webhook_url' => 'https://example.test/hook',
        ]);

        $engine->postPublished($post);

        $this->assertCount(1, $payloads);
        $this->assertSame('post_published', $payloads[0]['event']);
        $this->assertSame($post->id, $payloads[0]['post']['id']);

        $run = WorkflowRun::query()->firstOrFail();
        $this->assertSame(SocialPost::class, $run->subject_type);
        $this->assertSame($post->id, $run->subject_id);
    }

    public function test_a_failed_webhook_is_recorded_without_throwing(): void
    {
        $contact = Contact::create(['email' => 'broken@example.test']);
        $workflow = $this->workflow(WorkflowAction::Webhook, [
            'webhook_url' => 'https://example.test/hook',
        ]);

        $engine = new WorkflowEngine(function (): void {
            throw new RuntimeException('Endpoint refused the call.');
        });

        $engine->contactCreated($contact);

        $run = WorkflowRun::query()->firstOrFail();

        $this->assertSame(WorkflowRunStatus::Failed, $run->status);
        $this->assertSame('Endpoint refused the call.', $run->message);
        $this->assertSame(0, $workflow->fresh()->run_count);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function workflow(WorkflowAction $action, array $attributes = []): Workflow
    {
        return Workflow::create($attributes + [
            'name' => 'Test workflow',
            'trigger' => WorkflowTrigger::ContactCreated,
            'action' => $action,
            'is_active' => true,
        ]);
    }
}
