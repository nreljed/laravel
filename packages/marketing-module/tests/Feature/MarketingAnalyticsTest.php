<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Enums\CampaignObjective;
use Nreljed\MarketingModule\Enums\CampaignStatus;
use Nreljed\MarketingModule\Enums\WorkflowAction;
use Nreljed\MarketingModule\Enums\WorkflowRunStatus;
use Nreljed\MarketingModule\Enums\WorkflowTrigger;
use Nreljed\MarketingModule\Models\Campaign;
use Nreljed\MarketingModule\Models\Contact;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Models\Workflow;
use Nreljed\MarketingModule\Models\WorkflowRun;
use Nreljed\MarketingModule\Services\MarketingAnalytics;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

/**
 * The reporting numbers the analytics page renders.
 */
final class MarketingAnalyticsTest extends TestCase
{
    use BootsDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();

        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);
    }

    public function test_it_reports_delivery_campaign_and_workflow_activity(): void
    {
        $account = SocialAccount::create([
            'user_id' => 1,
            'platform' => 'linkedin',
            'account_id' => 'page-1',
            'account_name' => 'Acme',
        ]);

        $campaign = Campaign::create([
            'name' => 'Launch',
            'objective' => CampaignObjective::Awareness,
            'status' => CampaignStatus::Active,
        ]);

        $post = SocialPost::create([
            'user_id' => 1,
            'campaign_id' => $campaign->id,
            'body' => 'Launch day',
            'status' => 'published',
            'published_at' => now(),
        ]);

        SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $this->secondAccount(),
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
            'ran_at' => now(),
        ]);

        $analytics = (new MarketingAnalytics)->overview();

        $this->assertSame(1, $analytics['summary']['posts']);
        $this->assertSame(2, $analytics['summary']['deliveries']);
        $this->assertSame(1, $analytics['summary']['delivered']);
        $this->assertSame(1, $analytics['summary']['delivery_failures']);
        $this->assertSame(50.0, $analytics['summary']['success_rate']);
        $this->assertSame(1, $analytics['summary']['contacts']);
        $this->assertSame(1, $analytics['summary']['active_workflows']);

        $this->assertCount(1, $analytics['platforms']);
        $this->assertSame('linkedin', $analytics['platforms'][0]['platform']);
        $this->assertSame(2, $analytics['platforms'][0]['total']);
        $this->assertSame(1, $analytics['platforms'][0]['delivered']);
        $this->assertSame(1, $analytics['platforms'][0]['failed']);

        $this->assertCount(14, $analytics['series']);
        $this->assertSame(1, $analytics['series'][13]['count']);

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

    private function secondAccount(): int
    {
        return SocialAccount::create([
            'user_id' => 1,
            'platform' => 'linkedin',
            'account_id' => 'page-2',
            'account_name' => 'Acme EU',
        ])->id;
    }
}
