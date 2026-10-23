<?php

namespace Nreljed\MarketingModule\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Nreljed\MarketingModule\Contracts\SocialChannelAdapter;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Models\MarketingLink;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Services\PostPublisher;
use Nreljed\MarketingModule\Services\SocialChannelRegistry;
use Nreljed\MarketingModule\Services\TokenVault;
use Nreljed\MarketingModule\Services\TrackedLinkService;
use Nreljed\MarketingModule\Support\NormalizedError;
use Nreljed\MarketingModule\Support\PostContent;
use Nreljed\MarketingModule\Support\PublishResult;
use Nreljed\MarketingModule\Support\SocialCapabilities;
use Nreljed\MarketingModule\Support\TokenSet;
use Nreljed\MarketingModule\Tests\Concerns\BootsDatabase;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * The publishing pipeline: eligibility, capability gates, retries, partial
 * success and the post-level roll-up.
 */
final class PostPublisherTest extends TestCase
{
    use BootsDatabase;

    private PublishingAdapter $adapter;

    private PostPublisher $publisher;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabase();
        $this->now = CarbonImmutable::parse('2026-10-22 12:00:00');

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Author',
            'email' => 'author@example.test',
        ]);

        $this->adapter = new PublishingAdapter;

        $registry = new SocialChannelRegistry(
            ['default' => 'fake', 'drivers' => [
                'fake' => PublishingAdapter::class,
                'instagram' => PublishingAdapter::class,
            ]],
            fn (): SocialChannelAdapter => $this->adapter,
        );

        $this->publisher = new PostPublisher($registry, new TokenVault);
    }

    public function test_it_publishes_a_due_post_and_marks_it_published(): void
    {
        $account = $this->account();
        $post = $this->post([$account]);

        $summary = $this->publisher->publishDue($this->now);

        $this->assertSame(1, $summary['posts']);
        $this->assertSame(1, $summary['published']);
        $this->assertCount(1, $this->adapter->published);

        $post->refresh();
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);

        $target = $post->targets()->sole();
        $this->assertSame(DeliveryStatus::Published, $target->status);
        $this->assertSame('external-1', $target->external_post_id);
        $this->assertSame(1, $target->attempts);
        $this->assertNull($target->error_code);
    }

    public function test_it_does_not_publish_a_post_that_is_not_due_yet(): void
    {
        $post = $this->post([$this->account()], [
            'scheduled_at' => $this->now->addHour(),
        ]);

        $summary = $this->publisher->publishDue($this->now);

        $this->assertSame(0, $summary['posts']);
        $this->assertSame([], $this->adapter->published);
        $this->assertSame(PostStatus::Scheduled, $post->refresh()->status);
    }

    public function test_it_holds_a_post_until_it_is_approved(): void
    {
        $post = $this->post([$this->account()], [
            'requires_approval' => true,
            'approved_at' => null,
        ]);

        $this->publisher->publishDue($this->now);

        $this->assertSame([], $this->adapter->published);
        $this->assertSame(PostStatus::Scheduled, $post->refresh()->status);
        $this->assertSame(0, SocialPost::query()->due($this->now)->count(), 'the dry-run scope agrees with the publisher');

        $this->publisher->approve($post, $this->now);
        $this->assertSame(1, SocialPost::query()->due($this->now)->count());
        $this->publisher->publishDue($this->now);

        $this->assertCount(1, $this->adapter->published);
        $this->assertSame(PostStatus::Published, $post->refresh()->status);
    }

    public function test_a_retryable_failure_is_rescheduled_with_the_configured_backoff(): void
    {
        $this->adapter->results = [PublishResult::failed('rate_limited', 'Slow down.', true)];

        $post = $this->post([$this->account()]);

        $this->publisher->publishDue($this->now);

        $target = $post->refresh()->targets()->sole();

        $this->assertSame(DeliveryStatus::Pending, $target->status);
        $this->assertSame('rate_limited', $target->error_code);
        $this->assertSame(1, $target->attempts);
        $this->assertSame(
            $this->now->addSeconds(60)->getTimestamp(),
            $target->next_attempt_at?->getTimestamp(),
        );
        $this->assertSame(PostStatus::Publishing, $post->refresh()->status);
    }

    public function test_it_waits_for_the_backoff_before_trying_again(): void
    {
        $this->adapter->results = [PublishResult::failed('server_error', 'Down.', true)];

        $post = $this->post([$this->account()]);

        $this->publisher->publishDue($this->now);

        // Same minute: still inside the backoff window, so no second attempt.
        $this->publisher->publishDue($this->now->addSeconds(30));

        $this->assertCount(1, $this->adapter->published);
        $this->assertSame(1, $post->refresh()->targets()->sole()->attempts);

        // After the window the retry goes out and succeeds.
        $this->publisher->publishDue($this->now->addSeconds(61));

        $this->assertCount(2, $this->adapter->published);
        $this->assertSame(DeliveryStatus::Published, $post->refresh()->targets()->sole()->status);
        $this->assertSame(PostStatus::Published, $post->refresh()->status);
    }

    public function test_it_stops_retrying_after_the_configured_attempt_limit(): void
    {
        $this->adapter->results = [
            PublishResult::failed('server_error', 'Down.', true),
            PublishResult::failed('server_error', 'Still down.', true),
            PublishResult::failed('server_error', 'Still down.', true),
        ];

        $post = $this->post([$this->account()]);

        $this->publisher->publishDue($this->now);
        $this->publisher->publishDue($this->now->addMinutes(2));
        $this->publisher->publishDue($this->now->addMinutes(10));

        $target = $post->refresh()->targets()->sole();

        $this->assertSame(DeliveryStatus::Failed, $target->status);
        $this->assertSame(3, $target->attempts);
        $this->assertNull($target->next_attempt_at);
        $this->assertSame(PostStatus::Failed, $post->refresh()->status);

        // A fourth scheduler tick must not call the platform again.
        $this->publisher->publishDue($this->now->addHours(2));
        $this->assertCount(3, $this->adapter->published);
    }

    public function test_a_permanent_failure_is_not_retried(): void
    {
        $this->adapter->results = [PublishResult::failed('rejected', 'Not allowed.', false)];

        $post = $this->post([$this->account()]);

        $this->publisher->publishDue($this->now);

        $target = $post->refresh()->targets()->sole();

        $this->assertSame(DeliveryStatus::Failed, $target->status);
        $this->assertNull($target->next_attempt_at);
        $this->assertSame(PostStatus::Failed, $post->refresh()->status);
    }

    public function test_partial_success_is_a_first_class_outcome(): void
    {
        $this->adapter->results = [
            PublishResult::published('external-1'),
            PublishResult::failed('rejected', 'Not allowed.', false),
        ];

        $post = $this->post([$this->account(), $this->account()]);

        $summary = $this->publisher->publishDue($this->now);

        $this->assertSame(1, $summary['published']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(PostStatus::Partial, $post->refresh()->status);

        $targets = $post->refresh()->targets()->orderBy('id')->get();
        $this->assertSame(DeliveryStatus::Published, $targets[0]->status);
        $this->assertSame(DeliveryStatus::Failed, $targets[1]->status);

        // The successful delivery is never attempted a second time.
        $this->publisher->publishDue($this->now->addHours(2));
        $this->assertCount(2, $this->adapter->published);
    }

    public function test_it_refuses_content_the_platform_cannot_carry(): void
    {
        $this->adapter->capabilities = new SocialCapabilities(maxTextLength: 5);

        $post = $this->post([$this->account()], ['body' => 'This is far too long.']);

        $this->publisher->publishDue($this->now);

        $target = $post->refresh()->targets()->sole();

        $this->assertSame([], $this->adapter->published);
        $this->assertSame(DeliveryStatus::Failed, $target->status);
        $this->assertSame('unsupported_content', $target->error_code);
        $this->assertStringContainsString('allows 5', (string) $target->error_message);
    }

    public function test_an_adapter_exception_is_normalised_through_map_error(): void
    {
        $this->adapter->results = [new RuntimeException('socket exploded')];

        $post = $this->post([$this->account()]);

        $this->publisher->publishDue($this->now);

        $target = $post->refresh()->targets()->sole();

        $this->assertSame(DeliveryStatus::Failed, $target->status);
        $this->assertSame('unexpected', $target->error_code);
        $this->assertSame('socket exploded', $target->error_message);
    }

    public function test_publish_now_retries_a_post_that_failed_for_good(): void
    {
        $this->adapter->results = [PublishResult::failed('rejected', 'Not allowed.', false)];

        $post = $this->post([$this->account()]);

        $this->publisher->publishDue($this->now);
        $this->assertSame(PostStatus::Failed, $post->refresh()->status);

        $this->publisher->publishNow($post, $this->now->addMinutes(5));

        $target = $post->refresh()->targets()->sole();

        $this->assertCount(2, $this->adapter->published);
        $this->assertSame(DeliveryStatus::Published, $target->status);
        $this->assertSame(1, $target->attempts, 'publish now resets the attempt counter');
        $this->assertSame(PostStatus::Published, $post->refresh()->status);
    }

    public function test_publish_now_pulls_a_delivery_forward_out_of_its_backoff(): void
    {
        $this->adapter->results = [PublishResult::failed('rate_limited', 'Slow down.', true)];

        $post = $this->post([$this->account()]);

        $this->publisher->publishDue($this->now);
        $this->assertSame(DeliveryStatus::Pending, $post->refresh()->targets()->sole()->status);

        // The backoff window is still open, but an explicit publish now wins.
        $this->publisher->publishNow($post, $this->now->addSeconds(5));

        $target = $post->refresh()->targets()->sole();

        $this->assertCount(2, $this->adapter->published);
        $this->assertSame(DeliveryStatus::Published, $target->status);
        $this->assertSame(PostStatus::Published, $post->refresh()->status);
    }

    public function test_a_post_without_targets_fails_instead_of_looping_forever(): void
    {
        $post = SocialPost::create([
            'user_id' => 1,
            'body' => 'Nobody to tell.',
            'status' => PostStatus::Scheduled,
            'scheduled_at' => $this->now->subMinute(),
            'timezone' => 'UTC',
            'requires_approval' => false,
            'approved_at' => $this->now,
        ]);

        $this->publisher->publishDue($this->now);

        $this->assertSame(PostStatus::Failed, $post->refresh()->status);
        $this->assertSame([], $this->adapter->published);
    }

    public function test_it_never_reads_or_writes_a_plaintext_token(): void
    {
        $account = $this->account(['access_token_encrypted' => app(TokenVault::class)->encrypt('secret-token')]);

        $this->publisher->publishDue($this->now);

        $this->assertArrayNotHasKey('access_token_encrypted', $account->fresh()->toArray());
        $this->assertSame('secret-token', $account->fresh()->accessToken());
    }

    public function test_tracking_injects_a_per_platform_link_without_changing_the_post(): void
    {
        $account = $this->account(['platform' => 'instagram']);
        $post = $this->post([$account], [
            'link' => 'https://shop.test/offer',
            'track_clicks' => true,
        ]);

        $this->publisher->publishDue($this->now);

        $this->assertCount(1, $this->adapter->published);
        $this->assertSame(PostStatus::Published, $post->refresh()->status);

        $link = MarketingLink::query()->sole();
        $expected = (new TrackedLinkService)->trackedUrl($link);

        $this->assertSame('https://shop.test/offer', $post->refresh()->link);
        $this->assertSame('instagram', $link->utm_source);
        $this->assertSame('social', $link->utm_medium);
        $this->assertSame('post-'.$post->id.'-instagram', $link->utm_content);
        $this->assertSame($expected, $this->adapter->published[0]->link);
    }

    public function test_tracking_failure_does_not_block_publishing(): void
    {
        $post = $this->post([$this->account()], [
            'link' => 'ftp://shop.test/offer',
            'track_clicks' => true,
        ]);

        $this->publisher->publishDue($this->now);

        $this->assertSame('ftp://shop.test/offer', $this->adapter->published[0]->link);
        $this->assertSame(0, MarketingLink::query()->count());
        $this->assertSame(PostStatus::Published, $post->refresh()->status);
    }

    /**
     * @param  list<SocialAccount>  $accounts
     * @param  array<string, mixed>  $attributes
     */
    private function post(array $accounts, array $attributes = []): SocialPost
    {
        $post = SocialPost::create(array_merge([
            'user_id' => 1,
            'body' => 'Hello world',
            'status' => PostStatus::Scheduled,
            'scheduled_at' => $this->now->subMinute(),
            'timezone' => 'UTC',
            'requires_approval' => false,
            'approved_at' => $this->now,
        ], $attributes));

        foreach ($accounts as $account) {
            SocialPostTarget::create([
                'social_post_id' => $post->id,
                'social_account_id' => $account->id,
            ]);
        }

        return $post;
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
            'status' => 'active',
            'metadata' => [],
        ], $attributes));
    }
}

/**
 * A adapter that records what it was asked to publish and replays a scripted
 * list of outcomes, so the pipeline's decisions are testable without HTTP.
 */
final class PublishingAdapter implements SocialChannelAdapter
{
    /**
     * A queue of PublishResult|Throwable, consumed one per publish call.
     *
     * @var list<PublishResult|Throwable>
     */
    public array $results = [];

    /** @var list<PostContent> */
    public array $published = [];

    public SocialCapabilities $capabilities;

    public function __construct()
    {
        $this->capabilities = new SocialCapabilities;
    }

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
        return $this->capabilities;
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
        return $tokens;
    }

    public function publish(PostContent $content, SocialAccount $account): PublishResult
    {
        $this->published[] = $content;

        $next = array_shift($this->results) ?? PublishResult::published('external-'.count($this->published));

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }

    public function fetchMetrics(string $externalPostId, SocialAccount $account): array
    {
        return [];
    }

    public function revoke(SocialAccount $account): void
    {
        //
    }

    public function mapError(Throwable $e): NormalizedError
    {
        return NormalizedError::permanent('unexpected', $e->getMessage());
    }
}
