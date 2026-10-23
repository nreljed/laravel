<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Nreljed\MarketingModule\Models\Campaign;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\MarketingLinkClick;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Services\TrackedLinkService;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

final class TrackedLinkServiceTest extends TestCase
{
    use BootsDatabase;

    private TrackedLinkService $links;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();

        $this->container['db']->table('users')->insert([
            'id' => 1,
            'name' => 'Author',
            'email' => 'author@example.test',
        ]);

        $this->links = new TrackedLinkService;
    }

    public function test_it_creates_one_tracked_link_per_target_with_platform_utms(): void
    {
        $campaign = Campaign::create(['name' => 'Autumn Sale']);
        $post = $this->post([
            'campaign_id' => $campaign->id,
            'link' => 'https://shop.test/offer?ref=social#details',
            'track_clicks' => true,
        ]);

        $instagram = $this->target($post, 'instagram');
        $linkedin = $this->target($post, 'linkedin');

        $instagramLink = $this->links->ensureForTarget($instagram, $post);
        $linkedinLink = $this->links->ensureForTarget($linkedin, $post);

        $this->assertNotNull($instagramLink);
        $this->assertNotNull($linkedinLink);
        $this->assertSame(2, MarketingLink::query()->count());
        $this->assertNotSame($instagramLink->short_code, $linkedinLink->short_code);

        $this->assertSame('https://shop.test/offer?ref=social#details', $post->refresh()->link);
        $this->assertSame('instagram', $instagramLink->utm_source);
        $this->assertSame('linkedin', $linkedinLink->utm_source);
        $this->assertSame('social', $instagramLink->utm_medium);
        $this->assertSame('autumn-sale', $instagramLink->utm_campaign);
        $this->assertSame('post-'.$post->id.'-instagram', $instagramLink->utm_content);

        $destination = $this->links->destinationUrl($instagramLink);

        $this->assertNotNull($destination);
        $this->assertStringStartsWith('https://shop.test/offer?ref=social&', $destination);
        $this->assertStringEndsWith('#details', $destination);

        parse_str((string) parse_url($destination, PHP_URL_QUERY), $query);

        $this->assertSame('social', $query['ref']);
        $this->assertSame('instagram', $query['utm_source']);
        $this->assertSame('social', $query['utm_medium']);
        $this->assertSame('autumn-sale', $query['utm_campaign']);
        $this->assertSame('post-'.$post->id.'-instagram', $query['utm_content']);

        $this->assertSame(
            'https://laravel.test/m/'.$instagramLink->short_code,
            $this->links->trackedUrl($instagramLink),
        );
    }

    public function test_it_reuses_the_target_link_and_code_on_retry(): void
    {
        $post = $this->post([
            'link' => 'https://shop.test/offer',
            'track_clicks' => true,
        ]);
        $target = $this->target($post, 'x');

        $first = $this->links->ensureForTarget($target, $post);
        $second = $this->links->ensureForTarget($target, $post);

        $this->assertSame($first?->id, $second?->id);
        $this->assertSame($first?->short_code, $second?->short_code);
        $this->assertSame(1, MarketingLink::query()->count());
    }

    public function test_it_does_not_create_links_when_tracking_is_disabled(): void
    {
        $post = $this->post([
            'link' => 'https://shop.test/offer',
            'track_clicks' => false,
        ]);
        $target = $this->target($post, 'instagram');

        $this->assertNull($this->links->ensureForTarget($target, $post));
        $this->assertSame(0, MarketingLink::query()->count());
    }

    public function test_it_rejects_unsafe_or_relative_destinations(): void
    {
        $urls = [
            'ftp://shop.test/offer',
            '//evil.test/offer',
            'javascript:alert(1)',
            'https://user:pass@shop.test/offer',
        ];

        foreach ($urls as $index => $url) {
            $post = $this->post([
                'link' => $url,
                'track_clicks' => true,
            ]);
            $target = $this->target($post, 'instagram', $index + 1);

            try {
                $this->links->ensureForTarget($target, $post);
                $this->fail('Expected a rejected destination: '.$url);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, MarketingLink::query()->count());
    }

    public function test_it_records_a_click_without_storing_the_raw_ip_or_unbounded_headers(): void
    {
        $post = $this->post([
            'link' => 'https://shop.test/offer',
            'track_clicks' => true,
        ]);
        $target = $this->target($post, 'instagram');
        $link = $this->links->ensureForTarget($target, $post);

        $this->assertNotNull($link);

        $request = Request::create('/m/'.$link->short_code, 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_USER_AGENT' => str_repeat('a', 600),
            'HTTP_REFERER' => str_repeat('b', 3000),
        ]);

        $click = $this->links->recordClick($link, $request);

        $this->assertNotNull($click);
        $this->assertSame(1, MarketingLinkClick::query()->count());
        $this->assertSame(1, $link->refresh()->clicks_count);
        $this->assertNotNull($link->last_clicked_at);
        $this->assertSame(64, strlen((string) $click->ip_hash));
        $this->assertNotSame('203.0.113.9', $click->ip_hash);
        $this->assertSame(512, strlen((string) $click->user_agent));
        $this->assertSame(2048, strlen((string) $click->referer));
        $this->assertNull($click->country);
    }

    public function test_unknown_and_inactive_links_are_not_resolved(): void
    {
        $post = $this->post([
            'link' => 'https://shop.test/offer',
            'track_clicks' => true,
        ]);
        $target = $this->target($post, 'instagram');
        $link = $this->links->ensureForTarget($target, $post);

        $this->assertNotNull($link);
        $this->assertNull($this->links->findActive('missing'));
        $this->assertNotNull($this->links->findActive($link->short_code));

        $link->forceFill(['is_active' => false])->save();

        $this->assertNull($this->links->findActive($link->short_code));
    }

    public function test_a_failed_click_write_is_swallowed(): void
    {
        $post = $this->post([
            'link' => 'https://shop.test/offer',
            'track_clicks' => true,
        ]);
        $target = $this->target($post, 'instagram');
        $link = $this->links->ensureForTarget($target, $post);

        $this->assertNotNull($link);

        Schema::drop('marketing_link_clicks');

        $request = Request::create('/m/'.$link->short_code, 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.9',
        ]);

        $this->assertNull($this->links->recordClick($link, $request));
        $this->assertSame(0, $link->refresh()->clicks_count);
    }

    private function post(array $attributes = []): SocialPost
    {
        return SocialPost::create(array_merge([
            'user_id' => 1,
            'body' => 'Hello world',
            'status' => 'draft',
            'timezone' => 'UTC',
            'requires_approval' => false,
        ], $attributes));
    }

    private function target(SocialPost $post, string $platform, int $sequence = 1): SocialPostTarget
    {
        $account = SocialAccount::create([
            'user_id' => 1,
            'platform' => $platform,
            'account_id' => $platform.'-'.$sequence,
            'account_name' => ucfirst($platform).' account '.$sequence,
            'scopes' => [],
            'status' => 'active',
            'metadata' => [],
        ]);

        return SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
        ]);
    }
}
