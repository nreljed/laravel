<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Nreljed\MarketingModule\Models\MarketingConversion;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\MarketingLinkClick;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Services\ConversionIngestService;
use Nreljed\MarketingModule\Services\TrackedLinkService;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

final class ConversionIngestTest extends TestCase
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

    public function test_it_records_and_deduplicates_a_conversion(): void
    {
        $link = $this->link();

        $first = (new ConversionIngestService)->record([
            'short_code' => $link->short_code,
            'source' => 'shop',
            'event_type' => 'order.paid',
            'external_reference' => '42',
            'value' => '12.50',
            'currency' => 'usd',
            'occurred_at' => '2026-10-23 10:00:00',
        ]);

        $second = (new ConversionIngestService)->record([
            'short_code' => $link->short_code,
            'external_reference' => '42',
            'value' => '99.00',
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, MarketingConversion::query()->count());
        $this->assertSame('12.50', $first->value);
        $this->assertSame('USD', $first->currency);
        $this->assertSame($link->id, $first->marketing_link_id);
    }

    public function test_it_uses_last_click_attribution(): void
    {
        $link = $this->link();
        $click = MarketingLinkClick::create([
            'marketing_link_id' => $link->id,
            'clicked_at' => '2026-10-23 09:00:00',
        ]);

        $conversion = (new ConversionIngestService)->record([
            'link_id' => $link->id,
            'occurred_at' => '2026-10-23 10:00:00',
        ]);

        $this->assertSame($click->id, $conversion->marketing_link_click_id);
    }

    private function link(): MarketingLink
    {
        $post = SocialPost::create([
            'user_id' => 1,
            'body' => 'Launch day',
            'status' => 'published',
            'published_at' => now(),
            'link' => 'https://shop.test/offer',
            'track_clicks' => true,
        ]);

        $target = SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $this->account()->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        return (new TrackedLinkService)->ensureForTarget($target, $post);
    }

    private function account(): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => 1,
            'platform' => 'instagram',
            'account_name' => 'instagram account',
            'username' => 'instagram-user',
            'account_id' => 'page-1',
            'status' => 'active',
        ]);
    }
}
