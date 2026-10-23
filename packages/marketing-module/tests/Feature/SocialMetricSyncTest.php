<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Nreljed\MarketingModule\Contracts\SocialChannelAdapter;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\MetricSyncStatus;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\MarketingModule\Exceptions\MetricFetchException;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostMetric;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Services\AccountTokenRefresher;
use Nreljed\MarketingModule\Services\MetricNormalizer;
use Nreljed\MarketingModule\Services\SocialChannelRegistry;
use Nreljed\MarketingModule\Services\SocialMetricSync;
use Nreljed\MarketingModule\Services\TokenVault;
use Nreljed\MarketingModule\Support\NormalizedError;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;
use Throwable;

final class SocialMetricSyncTest extends TestCase
{
    use BootsDatabase;

    private MetricsAdapter $adapter;

    private SocialMetricSync $sync;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
        $this->now = CarbonImmutable::parse('2026-10-23 12:00:00');

        DB::table('users')->insert(['id' => 1, 'name' => 'Author', 'email' => 'author@example.test']);

        $this->adapter = new MetricsAdapter;

        $registry = new SocialChannelRegistry(
            ['default' => 'fake', 'drivers' => ['fake' => MetricsAdapter::class]],
            fn (): SocialChannelAdapter => $this->adapter,
        );

        $this->sync = new SocialMetricSync(
            $registry,
            new MetricNormalizer,
            new AccountTokenRefresher(new TokenVault),
        );
    }

    public function test_it_stores_a_daily_snapshot_and_marks_the_delivery_synced(): void
    {
        $target = $this->target();
        $this->adapter->metrics = [
            'impressions' => 100,
            'reactions' => 0,
            'post_clicks' => 5,
            'pin_saves' => 2,
        ];

        $summary = $this->sync->sync($this->now);

        $this->assertSame(1, $summary['selected']);
        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['ok']);

        $target->refresh();
        $this->assertSame(MetricSyncStatus::Ok, $target->metrics_status);
        $this->assertNotNull($target->metrics_attempted_at);
        $this->assertNotNull($target->metrics_synced_at);
        $this->assertNull($target->metrics_error);

        $metric = SocialPostMetric::query()->sole();
        $this->assertSame($target->id, $metric->social_post_target_id);
        $this->assertSame('2026-10-23', $metric->metric_date->toDateString());
        $this->assertSame(100, $metric->impressions);
        $this->assertSame(0, $metric->likes);
        $this->assertSame(5, $metric->clicks);
        $this->assertSame(['pin_saves' => 2], $metric->custom);
        $this->assertSame('fake', $metric->source);
        $this->assertSame(1, $target->post->metrics()->count());
        $this->assertSame($metric->id, $target->latestMetric?->id);
    }

    public function test_a_forced_second_sync_updates_the_same_daily_row(): void
    {
        $target = $this->target();
        $this->adapter->metrics = ['impressions' => 10];

        $this->sync->sync($this->now);

        $this->adapter->metrics = ['impressions' => 25];
        $later = $this->now->addHours(2);
        $second = $this->sync->sync($later, force: true);

        $this->assertSame(1, $second['selected'], 'second sync selection');
        $this->assertSame(1, $second['ok'], 'second sync ok count');
        $this->assertSame(2, $this->adapter->fetchCalls, 'second sync fetch calls');
        $this->assertSame(1, SocialPostMetric::query()->count());

        $metric = SocialPostMetric::query()->sole();
        $this->assertSame(25, $metric->impressions);
        $this->assertTrue($metric->captured_at->equalTo($later));
    }

    public function test_a_successful_empty_response_is_unavailable_not_zero(): void
    {
        $target = $this->target();
        $this->adapter->metrics = [];

        $summary = $this->sync->sync($this->now);

        $this->assertSame(1, $summary['unavailable']);

        $target->refresh();
        $this->assertSame(MetricSyncStatus::Unavailable, $target->metrics_status);
        $this->assertNull($target->metrics_synced_at);
        $this->assertSame(0, SocialPostMetric::query()->count());
    }

    public function test_a_failed_sync_keeps_the_previous_snapshot_and_expires_the_account(): void
    {
        $target = $this->target();
        $this->adapter->metrics = ['impressions' => 10];

        $this->sync->sync($this->now);

        $this->adapter->throws = MetricFetchException::fromNormalized(
            NormalizedError::permanent('token_rejected', 'The platform rejected the token.'),
        );

        $summary = $this->sync->sync($this->now->addHour(), force: true);

        $this->assertSame(1, $summary['errors']);

        $target->refresh();
        $this->assertSame(MetricSyncStatus::Error, $target->metrics_status);
        $this->assertSame('The platform rejected the token.', $target->metrics_error);
        $this->assertSame(SocialAccountStatus::Expired, $target->account->status);
        $this->assertSame(1, SocialPostMetric::query()->count());
        $this->assertSame(10, SocialPostMetric::query()->sole()->impressions);
    }

    public function test_it_refreshes_an_expiring_account_once(): void
    {
        $vault = app(TokenVault::class);

        $account = $this->account([
            'access_token_encrypted' => $vault->encrypt('old-access'),
            'refresh_token_encrypted' => $vault->encrypt('old-refresh'),
            'expires_at' => $this->now->subDay(),
        ]);

        $this->adapter->refreshed = TokenSet::make(
            'new-access',
            'new-refresh',
            $this->now->addHour()->getTimestamp(),
        );
        $this->adapter->metrics = ['impressions' => 3];

        $this->target($account);

        $this->sync->sync($this->now);

        $this->assertSame(1, $this->adapter->refreshCalls);

        $account->refresh();
        $this->assertSame('new-access', $account->accessToken());
        $this->assertSame('new-refresh', $account->refreshToken());
        $this->assertTrue($account->expires_at->greaterThan($this->now));
    }

    public function test_selection_respects_the_window_and_platform(): void
    {
        $this->target(publishedAt: $this->now->subDays(2));
        $this->target(publishedAt: $this->now->subDays(40));
        $this->target($this->account(['platform' => 'other']));

        $summary = $this->sync->sync($this->now, days: 30, platform: 'fake');

        $this->assertSame(1, $summary['selected']);
        $this->assertSame(1, $summary['processed']);
    }

    public function test_limit_and_dry_run_do_not_call_the_provider(): void
    {
        $this->target(publishedAt: $this->now->subDay());
        $this->target(publishedAt: $this->now->subDays(2));

        $summary = $this->sync->sync($this->now, limit: 1, dryRun: true);

        $this->assertTrue($summary['dry_run']);
        $this->assertSame(1, $summary['selected']);
        $this->assertSame(0, $summary['processed']);
        $this->assertSame(0, $this->adapter->fetchCalls);
        $this->assertSame(0, SocialPostMetric::query()->count());
    }

    public function test_force_ignores_a_fresh_successful_snapshot(): void
    {
        $target = $this->target();
        $this->adapter->metrics = ['impressions' => 10];

        $this->sync->sync($this->now);

        $this->assertSame(0, $this->sync->sync($this->now->addMinute())['selected']);
        $this->assertSame(1, $this->sync->sync($this->now->addMinute(), force: true)['selected']);
    }

    public function test_an_unsupported_platform_is_recorded_as_an_error(): void
    {
        $target = $this->target($this->account(['platform' => 'unsupported']));

        $summary = $this->sync->sync($this->now);

        $this->assertSame(1, $summary['errors']);

        $target->refresh();
        $this->assertSame(MetricSyncStatus::Error, $target->metrics_status);
        $this->assertStringContainsString('unsupported', (string) $target->metrics_error);
    }

    private function target(
        ?SocialAccount $account = null,
        ?CarbonImmutable $publishedAt = null,
    ): SocialPostTarget {
        $account ??= $this->account();
        $publishedAt ??= $this->now->subHour();

        $post = SocialPost::create([
            'user_id' => 1,
            'body' => 'Published post',
            'status' => PostStatus::Published,
            'scheduled_at' => $publishedAt,
            'published_at' => $publishedAt,
            'requires_approval' => false,
            'approved_at' => $publishedAt,
        ]);

        return SocialPostTarget::create([
            'social_post_id' => $post->id,
            'social_account_id' => $account->id,
            'status' => DeliveryStatus::Published,
            'attempts' => 1,
            'external_post_id' => 'external-'.$post->id,
            'published_at' => $publishedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function account(array $attributes = []): SocialAccount
    {
        static $sequence = 0;

        $sequence++;

        return SocialAccount::create(array_merge([
            'user_id' => 1,
            'platform' => 'fake',
            'account_id' => 'account-'.$sequence,
            'account_name' => 'Test account '.$sequence,
            'username' => 'test'.$sequence,
            'scopes' => [],
            'status' => SocialAccountStatus::Active,
            'metadata' => [],
        ], $attributes));
    }
}

/**
 * A scriptable adapter: the sync service is tested without HTTP.
 */
final class MetricsAdapter implements SocialChannelAdapter
{
    /** @var array<string, int|float> */
    public array $metrics = [];

    public ?Throwable $throws = null;

    public ?TokenSet $refreshed = null;

    public int $fetchCalls = 0;

    public int $refreshCalls = 0;

    public function platform(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake';
    }

    public function capabilities(): SocialCapabilities
    {
        return new SocialCapabilities;
    }

    public function requiredScopes(): array
    {
        return [];
    }

    public function socialiteDriver(): string
    {
        return 'fake';
    }

    public function hydrateTokenSet(SocialiteUser $user): TokenSet
    {
        return TokenSet::make($user->token);
    }

    public function discoverAccounts(TokenSet $tokens): array
    {
        return [];
    }

    public function refreshTokens(TokenSet $tokens): TokenSet
    {
        $this->refreshCalls++;

        return $this->refreshed ?? $tokens;
    }

    public function publish(PostContent $content, SocialAccount $account): PublishResult
    {
        return PublishResult::published('external-1');
    }

    public function fetchMetrics(string $externalPostId, SocialAccount $account): array
    {
        $this->fetchCalls++;

        if ($this->throws !== null) {
            throw $this->throws;
        }

        return $this->metrics;
    }

    public function revoke(SocialAccount $account): void
    {
        //
    }

    public function mapError(Throwable $e): NormalizedError
    {
        if ($e instanceof MetricFetchException) {
            return new NormalizedError(
                $e->errorCode,
                $e->getMessage(),
                $e->retryable,
                $e->retryAfter !== null,
                $e->retryAfter,
            );
        }

        return NormalizedError::permanent('unexpected', $e->getMessage());
    }
}
