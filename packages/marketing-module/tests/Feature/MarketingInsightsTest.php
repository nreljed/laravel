<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Models\MarketingBrief;
use Nreljed\MarketingModule\Models\MarketingInsight;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Services\MarketingAnalytics;
use Nreljed\MarketingModule\Services\MarketingInsightService;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use Nreljed\Platform\Models\User;
use PHPUnit\Framework\TestCase;

final class MarketingInsightsTest extends TestCase
{
    use BootsDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-23 12:00:00'));
        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_refresh_creates_a_coverage_insight_and_apply_creates_a_draft_brief(): void
    {
        $account = $this->account('linkedin', 'page-1');
        $post = SocialPost::create([
            'user_id' => 1,
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

        $created = (new MarketingInsightService(new MarketingAnalytics))->refresh(30);

        $this->assertCount(1, $created);
        $this->assertSame('coverage', $created[0]->type->value);

        $user = (new User)->forceFill(['id' => 1]);
        (new MarketingInsightService(new MarketingAnalytics))->apply($created[0], $user);

        $this->assertSame('applied', MarketingInsight::query()->first()->status->value);
        $this->assertSame(1, MarketingBrief::query()->count());
    }

    private function account(string $platform, string $externalId): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => 1,
            'platform' => $platform,
            'account_name' => $platform.' account',
            'username' => $platform.'-user',
            'account_id' => $externalId,
            'status' => 'active',
        ]);
    }
}
