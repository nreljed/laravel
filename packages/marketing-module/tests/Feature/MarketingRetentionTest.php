<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Models\MarketingLinkClick;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostMetric;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Models\WorkflowRun;
use Nreljed\MarketingModule\Services\MarketingRetention;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

final class MarketingRetentionTest extends TestCase
{
    use BootsDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
        $this->now = CarbonImmutable::parse('2026-10-25 12:00:00');

        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);

        $this->container['config']->set('marketing.metrics.retention_days', 30);
        $this->container['config']->set('marketing.links.retention_days', 10);
        $this->container['config']->set('marketing.workflows.retention_days', 5);
    }

    public function test_it_prunes_only_rows_past_their_window(): void
    {
        $target = $this->target();

        $oldMetric = SocialPostMetric::create([
            'social_post_target_id' => $target->id,
            'metric_date' => $this->now->subDays(31),
            'captured_at' => $this->now->subDays(31),
            'impressions' => 10,
        ]);
        $freshMetric = SocialPostMetric::create([
            'social_post_target_id' => $target->id,
            'metric_date' => $this->now->subDays(5),
            'captured_at' => $this->now->subDays(5),
            'impressions' => 20,
        ]);

        $linkId = DB::table('marketing_links')->insertGetId([
            'destination_url' => 'https://example.test/landing',
            'short_code' => 'abc123',
            'clicks_count' => 0,
            'is_active' => true,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);

        $oldClick = MarketingLinkClick::create([
            'marketing_link_id' => $linkId,
            'clicked_at' => $this->now->subDays(11),
        ]);
        $freshClick = MarketingLinkClick::create([
            'marketing_link_id' => $linkId,
            'clicked_at' => $this->now->subDays(2),
        ]);

        $workflowId = $this->workflow();

        $oldRun = $this->workflowRun($workflowId, WorkflowRunStatus::Success, $this->now->subDays(6));
        $freshRun = $this->workflowRun($workflowId, WorkflowRunStatus::Failed, $this->now->subDays(1));
        $pendingRun = $this->workflowRun($workflowId, WorkflowRunStatus::Pending, $this->now->subDays(6));

        $retention = new MarketingRetention;

        $plan = $retention->plan($this->now);

        $this->assertSame(1, $plan['metrics']);
        $this->assertSame(1, $plan['clicks']);
        $this->assertSame(1, $plan['workflow_runs']);

        // A dry run reports the same counts without deleting anything.
        $dryRun = $retention->prune(dryRun: true, now: $this->now);

        $this->assertTrue($dryRun['dry_run']);
        $this->assertSame($plan['metrics'], $dryRun['metrics']);
        $this->assertSame(2, SocialPostMetric::query()->count());
        $this->assertSame(2, MarketingLinkClick::query()->count());
        $this->assertSame(3, WorkflowRun::query()->count());
        $this->assertTrue($oldMetric->exists);
        $this->assertTrue($oldClick->exists);
        $this->assertTrue($oldRun->exists);

        $pruned = $retention->prune(now: $this->now);

        $this->assertFalse($pruned['dry_run']);
        $this->assertSame(1, $pruned['metrics']);
        $this->assertSame(1, $pruned['clicks']);
        $this->assertSame(1, $pruned['workflow_runs']);

        $this->assertFalse(SocialPostMetric::query()->whereKey($oldMetric->id)->exists());
        $this->assertTrue(SocialPostMetric::query()->whereKey($freshMetric->id)->exists());

        $this->assertFalse(MarketingLinkClick::query()->whereKey($oldClick->id)->exists());
        $this->assertTrue(MarketingLinkClick::query()->whereKey($freshClick->id)->exists());

        $this->assertFalse(WorkflowRun::query()->whereKey($oldRun->id)->exists());
        $this->assertTrue(WorkflowRun::query()->whereKey($freshRun->id)->exists());
        $this->assertTrue(WorkflowRun::query()->whereKey($pendingRun->id)->exists(), 'pending runs are unfinished work');
    }

    private function target(): SocialPostTarget
    {
        $account = SocialAccount::create([
            'user_id' => 1,
            'platform' => 'fake',
            'account_id' => 'account-1',
            'account_name' => 'Test account',
            'username' => 'test',
            'scopes' => [],
            'status' => SocialAccountStatus::Active,
            'metadata' => [],
        ]);

        $post = SocialPost::create([
            'user_id' => 1,
            'body' => 'Published post',
            'status' => PostStatus::Published,
            'scheduled_at' => $this->now->subDay(),
            'published_at' => $this->now->subDay(),
            'requires_approval' => false,
            'approved_at' => $this->now->subDay(),
        ]);

        return SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => DeliveryStatus::Published,
            'attempts' => 1,
            'external_post_id' => 'external-1',
            'published_at' => $this->now->subDay(),
        ]);
    }

    private function workflow(): int
    {
        return DB::table('workflows')->insertGetId([
            'name' => 'Welcome',
            'trigger' => 'contact_created',
            'action' => 'add_tag',
            'is_active' => true,
            'run_count' => 0,
            'created_at' => $this->now,
            'updated_at' => $this->now,
        ]);
    }

    private function workflowRun(int $workflowId, WorkflowRunStatus $status, CarbonImmutable $ranAt): WorkflowRun
    {
        return WorkflowRun::create([
            'workflow_id' => $workflowId,
            'step_index' => 0,
            'event' => 'contact_created',
            'status' => $status,
            'message' => null,
            'ran_at' => $ranAt,
        ]);
    }
}
