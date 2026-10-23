<?php

namespace Nreljed\MarketingModule\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Enums\SocialAccountStatus;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Models\SocialPostTarget;
use Nreljed\MarketingModule\Support\PublishResult;
use Throwable;

/**
 * The publishing pipeline.
 *
 * It owns the decisions no adapter should make: whether a post is allowed out
 * yet, whether a platform can carry the content at all, how long to wait
 * before retrying, and when a post as a whole is done. Adapters only translate
 * a `PostContent` into one platform's API call.
 */
class PostPublisher
{
    protected AccountTokenRefresher $refresher;

    protected TrackedLinkService $links;

    public function __construct(
        protected SocialChannelRegistry $channels,
        TokenVault $vault,
        ?AccountTokenRefresher $refresher = null,
        ?TrackedLinkService $links = null,
    ) {
        $this->refresher = $refresher ?? new AccountTokenRefresher($vault);
        $this->links = $links ?? new TrackedLinkService;
    }

    /**
     * Publish every post that is due, once.
     *
     * @return array{posts: int, targets: int, published: int, failed: int, retrying: int}
     */
    public function publishDue(?CarbonInterface $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $summary = ['posts' => 0, 'targets' => 0, 'published' => 0, 'failed' => 0, 'retrying' => 0];

        $posts = SocialPost::query()
            ->due($now)
            ->with(['targets.account'])
            ->orderBy('scheduled_at')
            ->get();

        foreach ($posts as $post) {
            if (! $post->isDueAt($now)) {
                continue;
            }

            $before = $post->targets->pluck('status', 'id');

            $this->publishPost($post, $now);

            $summary['posts']++;

            foreach ($post->targets()->get() as $target) {
                $previous = $before[$target->id] ?? null;

                if ($previous === $target->status) {
                    continue;
                }

                $summary['targets']++;

                match ($target->status) {
                    DeliveryStatus::Published => $summary['published']++,
                    DeliveryStatus::Failed => $summary['failed']++,
                    DeliveryStatus::Pending => $summary['retrying']++,
                    default => null,
                };
            }
        }

        return $summary;
    }

    /**
     * Attempt every delivery on a post that is due at the given moment.
     */
    public function publishPost(SocialPost $post, ?CarbonInterface $now = null): void
    {
        $now ??= CarbonImmutable::now();

        $post->loadMissing('targets.account');

        if ($post->requiresApproval() && ! $post->isApproved()) {
            return;
        }

        // A post with no targets is malformed rather than pending: nothing
        // will ever make it publishable, so surface it instead of retrying it
        // on every scheduler tick forever.
        if ($post->targets->isEmpty()) {
            $post->forceFill(['status' => PostStatus::Failed])->save();

            return;
        }

        $due = $post->targets->filter(
            static fn (SocialPostTarget $target): bool => $target->isDueAt($now),
        );

        if ($due->isEmpty()) {
            $this->refreshPostStatus($post, $now);

            return;
        }

        $post->forceFill(['status' => PostStatus::Publishing])->save();

        foreach ($due as $target) {
            $this->publishTarget($target, $now);
        }

        $this->refreshPostStatus($post->refresh(), $now);
    }

    /**
     * Publish a post on demand, regardless of its schedule.
     *
     * An explicit "publish now" is itself the human approval the approval gate
     * asks for, and it retries deliveries that previously failed for good. A
     * delivery waiting out its backoff is pulled forward too: the human asked
     * for it now, not in fifteen minutes.
     */
    public function publishNow(SocialPost $post, ?CarbonInterface $now = null): void
    {
        $now ??= CarbonImmutable::now();

        $post->targets()
            ->whereIn('status', [
                DeliveryStatus::Pending->value,
                DeliveryStatus::Failed->value,
                DeliveryStatus::Skipped->value,
            ])
            ->update([
                'status' => DeliveryStatus::Pending->value,
                'attempts' => 0,
                'next_attempt_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);

        $post->forceFill([
            'status' => PostStatus::Scheduled,
            'scheduled_at' => $now,
            'approved_at' => $now,
            'published_at' => null,
        ])->save();

        $this->publishPost($post->refresh(), $now);
    }

    /**
     * Record a human approval without publishing before the scheduled time.
     */
    public function approve(SocialPost $post, ?CarbonInterface $now = null): void
    {
        $now ??= CarbonImmutable::now();

        $post->forceFill([
            'approved_at' => $now,
            'scheduled_at' => $post->scheduled_at ?? $now,
            'status' => $post->status === PostStatus::Draft ? PostStatus::Scheduled : $post->status,
        ])->save();
    }

    /**
     * One delivery attempt against one account.
     */
    public function publishTarget(SocialPostTarget $target, ?CarbonInterface $now = null): PublishResult
    {
        $now ??= CarbonImmutable::now();

        $post = $target->post;
        $account = $target->account;

        if (! $post instanceof SocialPost) {
            return $this->fail($target, 'post_missing', 'The post no longer exists.', $now);
        }

        if (! $account instanceof SocialAccount) {
            return $this->fail($target, 'account_missing', 'The social account no longer exists.', $now);
        }

        if ($account->status === SocialAccountStatus::Revoked) {
            return $this->fail($target, 'account_revoked', 'The account grant was revoked.', $now);
        }

        try {
            $adapter = $this->channels->driver($account->platform);
        } catch (InvalidArgumentException) {
            return $this->fail(
                $target,
                'unsupported_platform',
                "No adapter is configured for [{$account->platform}].",
                $now,
            );
        }

        $this->refresher->refreshIfNeeded($account, $adapter);

        $content = $post->toPostContent($this->trackedLink($target, $post, $account));

        $violations = $adapter->capabilities()->violations($content);

        if ($violations !== []) {
            return $this->fail($target, 'unsupported_content', implode(' ', $violations), $now);
        }

        $target->forceFill([
            'status' => DeliveryStatus::Publishing,
            'attempts' => $target->attempts + 1,
            'last_attempted_at' => $now,
            'next_attempt_at' => null,
        ])->save();

        try {
            $result = $adapter->publish($content, $account);
        } catch (Throwable $e) {
            $error = $adapter->mapError($e);

            $result = PublishResult::failed(
                $error->code,
                $error->message,
                $error->retryable,
                ['retry_after' => $error->retryAfter],
            );
        }

        return $result->successful
            ? $this->succeed($target, $result, $now)
            : $this->fail(
                $target,
                (string) ($result->errorCode ?? 'rejected'),
                (string) ($result->errorMessage ?? 'The platform rejected the post.'),
                $now,
                $result->retryable,
                $this->retryAfter($result),
            );
    }

    protected function trackedLink(
        SocialPostTarget $target,
        SocialPost $post,
        SocialAccount $account,
    ): ?string {
        if (! $post->track_clicks) {
            return null;
        }

        try {
            $link = $this->links->ensureForTarget($target, $post, $account);

            return $link !== null ? $this->links->trackedUrl($link) : null;
        } catch (Throwable $e) {
            try {
                Log::warning('marketing.link_tracking_failed', [
                    'post_id' => $post->getKey(),
                    'target_id' => $target->getKey(),
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable) {
            }

            return null;
        }
    }

    protected function succeed(SocialPostTarget $target, PublishResult $result, CarbonInterface $now): PublishResult
    {
        $target->forceFill([
            'status' => DeliveryStatus::Published,
            'external_post_id' => $result->externalPostId,
            'external_url' => $result->externalUrl,
            'error_code' => null,
            'error_message' => null,
            'next_attempt_at' => null,
            'published_at' => $now,
        ])->save();

        return $result;
    }

    protected function fail(
        SocialPostTarget $target,
        string $code,
        string $message,
        CarbonInterface $now,
        bool $retryable = false,
        ?int $retryAfter = null,
    ): PublishResult {
        $maxAttempts = max(1, (int) config('marketing.publishing.max_attempts', 3));
        $attempts = $target->attempts > 0 ? $target->attempts : 1;

        $willRetry = $retryable && $attempts < $maxAttempts;

        if ($willRetry) {
            $delay = $retryAfter ?? $this->backoff($attempts);

            $target->forceFill([
                'status' => DeliveryStatus::Pending,
                'next_attempt_at' => CarbonImmutable::instance($now)->addSeconds($delay),
            ]);
        } else {
            $target->forceFill([
                'status' => DeliveryStatus::Failed,
                'next_attempt_at' => null,
            ]);
        }

        $target->forceFill([
            'error_code' => $code,
            'error_message' => $message,
        ])->save();

        // A rejected grant will keep failing the same way. Marking the account
        // surfaces it on the accounts page instead of leaving the user to
        // discover it one failed post at a time.
        if (in_array($code, ['token_rejected', 'token_missing', 'account_revoked'], true)) {
            $target->account?->forceFill(['status' => SocialAccountStatus::Expired])->save();
        }

        return PublishResult::failed($code, $message, $willRetry, [
            'retry_after' => $willRetry ? $target->next_attempt_at?->getTimestamp() : null,
        ]);
    }

    /**
     * Seconds to wait before the next attempt, taken from the configured
     * backoff ladder and clamped to its last step.
     */
    protected function backoff(int $attempts): int
    {
        $ladder = array_values(array_filter(
            (array) config('marketing.publishing.retry_backoff', [60, 300, 900]),
            static fn ($seconds): bool => is_numeric($seconds) && (int) $seconds > 0,
        ));

        if ($ladder === []) {
            return 60;
        }

        return (int) $ladder[min(max($attempts - 1, 0), count($ladder) - 1)];
    }

    /**
     * A platform-supplied Retry-After wins over the local ladder.
     */
    protected function retryAfter(PublishResult $result): ?int
    {
        $value = $result->raw['retry_after'] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * Roll the post's status up from its deliveries.
     *
     * Only called once every due delivery has been attempted, so "no pending
     * targets" means the post is done - published, partial, or failed.
     */
    protected function refreshPostStatus(SocialPost $post, CarbonInterface $now): void
    {
        $targets = $post->targets()->get();

        $pending = $targets->filter(
            static fn (SocialPostTarget $target): bool => ! $target->isTerminal(),
        );

        if ($pending->isNotEmpty()) {
            $post->forceFill(['status' => PostStatus::Publishing])->save();

            return;
        }

        $published = $targets->filter(
            static fn (SocialPostTarget $target): bool => $target->isPublished(),
        )->count();

        $status = match (true) {
            $published > 0 && $published === $targets->count() => PostStatus::Published,
            $published > 0 => PostStatus::Partial,
            default => PostStatus::Failed,
        };

        $post->forceFill([
            'status' => $status,
            'published_at' => $status === PostStatus::Published
                ? ($post->published_at ?? $now)
                : $post->published_at,
        ])->save();
    }
}
