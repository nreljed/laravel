<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Enums\CampaignStatus;
use Nreljed\MarketingModule\Enums\MetricSyncStatus;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;
use Nreljed\MarketingModule\Models\Campaign;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\MarketingLinkClick;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostMetric;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Models\WorkflowRun;
use Nreljed\MarketingModule\Services\AnalyticsCsvExporter;
use Nreljed\MarketingModule\Services\MarketingAnalytics;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

/**
 * The reporting numbers the analytics page renders and the CSV exports.
 */
final class MarketingAnalyticsTest extends TestCase
{
    use BootsDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
        $this->now = CarbonImmutable::parse('2026-10-23 12:00:00');
        CarbonImmutable::setTestNow($this->now);

        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_reports_delivery_campaign_and_workflow_activity(): void
    {
        $account = $this->account('linkedin', 'page-1');
        $campaign = $this->campaign();

        $post = SocialPost::create([
            'user_id' => 1,
            'campaign_id' => $campaign->id,
            'body' => 'Launch day',
            'status' => 'published',
            'published_at' => $this->now,
        ]);

        SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => 'published',
            'published_at' => $this->now,
        ]);

        SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $this->account('linkedin', 'page-2')->id,
            'status' => 'failed',
        ]);

        Contact::create(['email' => 'reader@example.test']);

        $workflow = Workflow::create([
            'name' => 'Welcome',
            'trigger' => WorkflowTrigger::ContactCreated,
            'action' => WorkflowAction::Webhook,
            'webhook_url' => 'https://example.test/hook',
            'is_active' => true,
        ]);

        WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'event' => WorkflowTrigger::ContactCreated->value,
            'status' => WorkflowRunStatus::Success,
            'ran_at' => $this->now,
        ]);

        $analytics = (new MarketingAnalytics)->overview();

        $this->assertSame(30, $analytics['period']['days']);
        $this->assertSame('2026-09-24', $analytics['period']['start']);
        $this->assertSame('2026-10-23', $analytics['period']['end']);

        $this->assertSame(1, $analytics['summary']['posts']);
        $this->assertSame(2, $analytics['summary']['deliveries']);
        $this->assertSame(1, $analytics['summary']['delivered']);
        $this->assertSame(1, $analytics['summary']['delivery_failures']);
        $this->assertSame(50.0, $analytics['summary']['success_rate']);
        $this->assertSame(1, $analytics['summary']['contacts']);
        $this->assertSame(1, $analytics['summary']['campaigns']);
        $this->assertSame(1, $analytics['summary']['active_workflows']);

        $this->assertCount(1, $analytics['platforms']);
        $this->assertSame('linkedin', $analytics['platforms'][0]['platform']);
        $this->assertSame(2, $analytics['platforms'][0]['total']);
        $this->assertSame(1, $analytics['platforms'][0]['delivered']);
        $this->assertSame(1, $analytics['platforms'][0]['failed']);

        $this->assertCount(30, $analytics['series']);
        $this->assertSame(1, $analytics['series'][29]['delivered']);

        $this->assertCount(1, $analytics['campaigns']);
        $this->assertSame('Launch', $analytics['campaigns'][0]['name']);
        $this->assertSame(1, $analytics['campaigns'][0]['posts']);
        $this->assertSame(2, $analytics['campaigns'][0]['deliveries']);
        $this->assertSame(1, $analytics['campaigns'][0]['delivered']);
        $this->assertSame(50.0, $analytics['campaigns'][0]['delivery_rate']);

        $this->assertSame(1, $analytics['workflows']['active']);
        $this->assertSame(1, $analytics['workflows']['runs']);
        $this->assertSame(0, $analytics['workflows']['failures']);
        $this->assertSame('Welcome', $analytics['workflows']['recent'][0]['workflow']);
    }

    public function test_metrics_use_the_latest_snapshot_and_are_scoped_to_the_period(): void
    {
        $campaign = $this->campaign();
        $post = SocialPost::create([
            'user_id' => 1,
            'campaign_id' => $campaign->id,
            'body' => 'Measured post',
            'status' => 'published',
            'published_at' => $this->now,
        ]);

        $target = SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $this->account('linkedin', 'page-1')->id,
            'status' => 'published',
            'published_at' => $this->now,
            'metrics_status' => MetricSyncStatus::Ok,
            'metrics_synced_at' => $this->now,
        ]);

        $this->metric($target->id, $this->now->subDay(), [
            'impressions' => 100,
            'clicks' => 5,
            'likes' => 3,
            'comments' => 2,
        ]);

        $this->metric($target->id, $this->now, [
            'impressions' => 150,
            'clicks' => 9,
            'likes' => 4,
            'comments' => 6,
        ]);

        $oldPost = SocialPost::create([
            'user_id' => 1,
            'campaign_id' => $campaign->id,
            'body' => 'Older post',
            'status' => 'published',
            'published_at' => $this->now->subDays(60),
        ]);

        $oldTarget = SocialPostTarget::create([
            'social_post_id' => $oldPost->id,
            'social_account_id' => $this->account('x', 'old-page')->id,
            'status' => 'published',
            'published_at' => $this->now->subDays(60),
            'metrics_status' => MetricSyncStatus::Ok,
        ]);

        $this->metric($oldTarget->id, $this->now->subDays(60), [
            'impressions' => 9999,
            'likes' => 500,
        ]);

        $analytics = (new MarketingAnalytics)->overview(7);

        $this->assertSame(7, $analytics['period']['days']);
        $this->assertSame(1, $analytics['summary']['delivered']);
        $this->assertSame(150, $analytics['summary']['impressions']);
        $this->assertSame(10, $analytics['summary']['engagements']);
        $this->assertSame(9, $analytics['summary']['clicks']);
        $this->assertSame(6.7, $analytics['summary']['engagement_rate']);
        $this->assertSame(6.0, $analytics['summary']['click_through_rate']);

        $this->assertSame(1, $analytics['coverage']['with_metrics']);
        $this->assertSame(1, $analytics['coverage']['delivered']);
        $this->assertSame(0, $analytics['coverage']['pending']);
        $this->assertSame(100.0, $analytics['coverage']['coverage_rate']);

        $this->assertCount(7, $analytics['series']);
        $this->assertSame(1, $analytics['series'][6]['delivered']);
        $this->assertSame(150, $analytics['series'][6]['impressions']);
        $this->assertSame(10, $analytics['series'][6]['engagements']);
        $this->assertSame(9, $analytics['series'][6]['clicks']);

        $this->assertCount(1, $analytics['platforms']);
        $this->assertSame(150, $analytics['platforms'][0]['impressions']);

        $this->assertCount(1, $analytics['top_posts']);
        $this->assertSame('Measured post', $analytics['top_posts'][0]['body']);
        $this->assertSame(150, $analytics['top_posts'][0]['impressions']);

        $this->assertCount(1, $analytics['campaigns']);
        $this->assertSame(150, $analytics['campaigns'][0]['impressions']);
        $this->assertSame(1, $analytics['campaigns'][0]['posts']);

        $this->assertSame(1, $analytics['summary']['posts']);
    }

    public function test_links_are_aggregated_for_the_period(): void
    {
        $post = SocialPost::create([
            'user_id' => 1,
            'body' => 'Tracked post',
            'status' => 'published',
            'published_at' => $this->now,
        ]);

        $target = SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $this->account('linkedin', 'page-1')->id,
            'status' => 'published',
            'published_at' => $this->now,
        ]);

        $link = MarketingLink::create([
            'social_post_id' => $post->id,
            'social_post_target_id' => $target->id,
            'destination_url' => 'https://example.test/landing',
            'utm_campaign' => 'launch',
            'short_code' => 'abc12345',
        ]);

        $this->click($link->id, $this->now->subDay());
        $this->click($link->id, $this->now);
        $this->click($link->id, $this->now->subDays(45));

        $analytics = (new MarketingAnalytics)->overview(30);

        $this->assertCount(1, $analytics['links']);
        $this->assertSame(2, $analytics['links'][0]['clicks']);
        $this->assertSame(0, $analytics['links'][0]['conversions']);
        $this->assertSame('https://example.test/landing', $analytics['links'][0]['destination_url']);
        $this->assertSame('https://laravel.test/m/abc12345', $analytics['links'][0]['short_url']);
    }

    public function test_csv_exports_cover_each_dataset_and_guard_formulas(): void
    {
        $campaign = $this->campaign();
        $post = SocialPost::create([
            'user_id' => 1,
            'campaign_id' => $campaign->id,
            'body' => '=SUM(A1:A2)',
            'status' => 'published',
            'published_at' => $this->now,
        ]);

        $target = SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $this->account('linkedin', 'page-1')->id,
            'status' => 'published',
            'published_at' => $this->now,
            'metrics_status' => MetricSyncStatus::Ok,
        ]);

        $this->metric($target->id, $this->now, [
            'impressions' => 10,
            'likes' => 2,
        ]);

        $link = MarketingLink::create([
            'social_post_id' => $post->id,
            'social_post_target_id' => $target->id,
            'destination_url' => 'https://example.test/landing',
            'utm_campaign' => 'launch',
            'short_code' => 'abc12345',
        ]);

        $this->click($link->id, $this->now);

        $exporter = new AnalyticsCsvExporter(new MarketingAnalytics);

        $posts = $exporter->export('posts', 30)['contents'];
        $this->assertStringContainsString('"Post ID","Post","Campaign"', $posts);
        $this->assertStringContainsString('\'=SUM(A1:A2)', $posts);
        $this->assertStringContainsString('"LinkedIn"', $posts);

        $campaigns = $exporter->export('campaigns', 30)['contents'];
        $this->assertStringContainsString('"Campaign ID","Campaign","Status"', $campaigns);
        $this->assertStringContainsString('"Launch"', $campaigns);

        $links = $exporter->export('links', 30)['contents'];
        $this->assertStringContainsString('"Link ID","Short URL","Destination"', $links);
        $this->assertStringContainsString('https://laravel.test/m/abc12345', $links);
        $this->assertStringContainsString('"launch"', $links);
    }

    private function account(string $platform, string $accountId): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => 1,
            'platform' => $platform,
            'account_id' => $accountId,
            'account_name' => 'Acme',
        ]);
    }

    private function campaign(): Campaign
    {
        return Campaign::create([
            'name' => 'Launch',
            'objective' => CampaignObjective::Awareness,
            'status' => CampaignStatus::Active,
        ]);
    }

    /**
     * @param  array<string, int|null>  $values
     */
    private function metric(int $targetId, CarbonImmutable $date, array $values): SocialPostMetric
    {
        return SocialPostMetric::create([
            'social_post_target_id' => $targetId,
            'metric_date' => $date->toDateString(),
            'captured_at' => $date,
            ...$values,
        ]);
    }

    private function click(int $linkId, CarbonImmutable $at): MarketingLinkClick
    {
        return MarketingLinkClick::create([
            'marketing_link_id' => $linkId,
            'clicked_at' => $at,
        ]);
    }
}
