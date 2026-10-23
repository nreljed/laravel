<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Nreljed\MarketingModule\Adapters\InstagramAdapter;
use Nreljed\MarketingModule\Adapters\LinkedInAdapter;
use Nreljed\MarketingModule\Adapters\MetaAdapter;
use Nreljed\MarketingModule\Adapters\PinterestAdapter;
use Nreljed\MarketingModule\Adapters\XAdapter;
use Nreljed\MarketingModule\Exceptions\MetricFetchException;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Services\TokenVault;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;

final class AdapterMetricSemanticsTest extends TestCase
{
    use BootsDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();

        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);
    }

    public function test_transport_and_auth_failures_throw_instead_of_returning_empty(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'The token is invalid.'],
            ], 401),
        ]);

        $this->expectException(MetricFetchException::class);
        $this->expectExceptionMessage('The token is invalid.');

        (new MetaAdapter)->fetchMetrics('post-1', $this->account());
    }

    public function test_a_missing_token_throws(): void
    {
        $this->expectException(MetricFetchException::class);

        (new MetaAdapter)->fetchMetrics('post-1', $this->account(withToken: false));
    }

    public function test_a_successful_response_without_data_returns_empty(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['data' => []], 200),
        ]);

        $this->assertSame([], (new MetaAdapter)->fetchMetrics('post-1', $this->account()));
    }

    public function test_a_reported_zero_is_returned_as_zero(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'data' => [[
                    'name' => 'post_impressions',
                    'values' => [['total' => 0]],
                ]],
            ], 200),
        ]);

        $this->assertSame(
            ['impressions' => 0],
            (new MetaAdapter)->fetchMetrics('post-1', $this->account()),
        );
    }

    public function test_instagram_keeps_a_reported_zero_and_omits_a_missing_total(): void
    {
        Http::fake([
            'graph.instagram.com/*' => Http::response([
                'data' => [
                    ['name' => 'impressions', 'values' => [['total' => 0]]],
                    ['name' => 'reach', 'values' => [[]]],
                ],
            ], 200),
        ]);

        $this->assertSame(
            ['impressions' => 0],
            (new InstagramAdapter)->fetchMetrics('post-1', $this->account(platform: 'instagram')),
        );
    }

    public function test_x_returns_empty_when_public_metrics_are_absent_or_empty(): void
    {
        Http::fake([
            'api.x.com/*' => Http::response(['data' => ['public_metrics' => []]], 200),
        ]);

        $this->assertSame(
            [],
            (new XAdapter)->fetchMetrics('post-1', $this->account(platform: 'x')),
        );
    }

    public function test_x_omits_metrics_that_were_not_returned(): void
    {
        Http::fake([
            'api.x.com/*' => Http::response([
                'data' => ['public_metrics' => ['like_count' => 0, 'reply_count' => 2]],
            ], 200),
        ]);

        $this->assertSame(
            ['likes' => 0, 'replies' => 2],
            (new XAdapter)->fetchMetrics('post-1', $this->account(platform: 'x')),
        );
    }

    public function test_linkedin_returns_empty_when_the_social_actions_are_empty(): void
    {
        Http::fake([
            'api.linkedin.com/*' => Http::response(['elements' => []], 200),
        ]);

        $this->assertSame(
            [],
            (new LinkedInAdapter)->fetchMetrics('post-1', $this->account(platform: 'linkedin')),
        );
    }

    public function test_pinterest_keeps_nested_zeroes(): void
    {
        Http::fake([
            'api.pinterest.com/*' => Http::response([
                'all' => [
                    'summary_metrics' => [
                        'IMPRESSION' => 0,
                        'SAVE' => 2,
                    ],
                ],
            ], 200),
        ]);

        $this->assertSame(
            ['IMPRESSION' => 0, 'SAVE' => 2],
            (new PinterestAdapter)->fetchMetrics('post-1', $this->account(platform: 'pinterest')),
        );
    }

    private function account(bool $withToken = true, string $platform = 'meta'): SocialAccount
    {
        $token = $withToken
            ? app(TokenVault::class)->encrypt('test-token')
            : null;

        return SocialAccount::create([
            'user_id' => 1,
            'platform' => $platform,
            'account_id' => 'page-1',
            'account_name' => 'Page',
            'access_token_encrypted' => $token,
            'scopes' => [],
            'status' => 'active',
            'metadata' => [],
        ]);
    }
}
