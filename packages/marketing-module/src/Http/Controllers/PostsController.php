<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nreljed\MarketingModule\Enums\DeliveryStatus;
use Nreljed\MarketingModule\Enums\PostStatus;
use Nreljed\MarketingModule\Models\SocialAccount;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Requests\StoreSocialPostRequest;
use Nreljed\MarketingModule\Resources\SocialPostResource;
use Nreljed\MarketingModule\Services\PostPublisher;
use Nreljed\MarketingModule\Support\PostMedia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;

/**
 * The composer.
 *
 * A post is content plus the accounts it addresses; every delivery's state
 * lives on its target, so this controller writes the post, syncs the targets,
 * and leaves the retrying to the publisher.
 */
class PostsController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', SocialPost::class);

        $posts = SocialPost::query()
            ->withCount('targets')
            ->withCount([
                'targets as published_targets_count' => fn ($query) => $query
                    ->where('status', DeliveryStatus::Published->value),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SocialPost $post): array => $this->row($post))
            ->all();

        return $renderer->renderIndex(SocialPostResource::KEY, $posts);
    }

    public function create(ResourceRenderer $renderer)
    {
        $this->authorize('create', SocialPost::class);

        return $renderer->renderForm(
            SocialPostResource::KEY,
            null,
            'POST',
            route('dashboard.marketing.posts.store'),
        );
    }

    public function store(StoreSocialPostRequest $request)
    {
        $this->authorize('create', SocialPost::class);

        $data = $request->validated();
        $accountIds = $this->accountIds($data);

        $post = DB::transaction(function () use ($data, $accountIds, $request): SocialPost {
            $post = SocialPost::create($this->attributes($data) + [
                'user_id' => $request->user()->id,
            ]);

            $this->syncTargets($post, $accountIds);

            return $post;
        });

        return redirect()
            ->to(route('dashboard.marketing.posts.index'))
            ->with('status', $post->status === PostStatus::Draft ? 'Post saved as a draft.' : 'Post scheduled.');
    }

    public function edit(ResourceRenderer $renderer, SocialPost $record)
    {
        $this->authorize('update', $record);

        return $renderer->renderForm(
            SocialPostResource::KEY,
            $this->row($record->loadCount('targets')),
            'PUT',
            route('dashboard.marketing.posts.update', $record),
        );
    }

    public function update(StoreSocialPostRequest $request, SocialPost $record)
    {
        $this->authorize('update', $record);

        $data = $request->validated();
        $accountIds = $this->accountIds($data);

        DB::transaction(function () use ($record, $data, $accountIds): void {
            $record->update($this->attributes($data, $record));

            $this->syncTargets($record, $accountIds);
        });

        return redirect()
            ->to(route('dashboard.marketing.posts.index'))
            ->with('status', 'Post updated.');
    }

    public function destroy(SocialPost $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Post deleted.');
    }

    /**
     * Try every delivery now, regardless of the schedule.
     */
    public function publish(SocialPost $record, PostPublisher $publisher)
    {
        $this->authorize('publish', $record);

        $publisher->publishNow($record);

        return redirect()->back()->with('status', 'Publish attempt finished.');
    }

    /**
     * Clear the approval gate without pulling the post forward.
     */
    public function approve(SocialPost $record, PostPublisher $publisher)
    {
        $this->authorize('publish', $record);

        $publisher->approve($record);

        return redirect()->back()->with('status', 'Post approved.');
    }

    /**
     * The accounts the current user may address, for the composer's picker.
     */
    public function accountOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SocialPost::class);

        $options = SocialAccount::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('platform')
            ->orderBy('account_name')
            ->get()
            ->map(static fn (SocialAccount $account): array => [
                'value' => $account->id,
                'label' => trim(sprintf(
                    '%s - %s%s',
                    ucfirst($account->platform),
                    $account->account_name,
                    $account->username ? ' (@'.$account->username.')' : '',
                )),
            ])
            ->all();

        return response()->json($options);
    }

    /**
     * The row shape the generic index and form pages consume.
     *
     * @return array<string, mixed>
     */
    protected function row(SocialPost $post): array
    {
        $timezone = $post->timezone ?: (string) config('marketing.publishing.timezone', 'UTC');

        return [
            'id' => $post->id,
            'preview' => Str::limit(trim((string) $post->body), 80),
            'body' => $post->body,
            'media' => array_map(
                static fn (array $item): array => [
                    'url' => $item['url'],
                    'alt' => $item['title'] ?? '',
                    'order' => $item['order'] ?? 0,
                ],
                PostMedia::normalize((array) ($post->media ?? [])),
            ),
            'link' => $post->link,
            'hashtags' => implode(', ', (array) ($post->hashtags ?? [])),
            'status' => $post->status?->label(),
            'delivery' => sprintf(
                '%d of %d',
                (int) ($post->published_targets_count ?? $post->targets()->where('status', DeliveryStatus::Published->value)->count()),
                (int) ($post->targets_count ?? $post->targets()->count()),
            ),
            'account_ids' => $post->targets()->pluck('social_account_id')->map(fn ($id): int => (int) $id)->all(),
            'scheduled_at' => $post->scheduled_at?->timezone($timezone)->format('Y-m-d\TH:i'),
            'published_at' => $post->published_at?->toDateTimeString(),
            'requires_approval' => (bool) $post->requires_approval,
            'approved_at' => $post->approved_at?->toDateTimeString(),
        ];
    }

    /**
     * Turn the validated form payload into post columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function attributes(array $data, ?SocialPost $post = null): array
    {
        $timezone = (string) config('marketing.publishing.timezone', 'UTC');

        $scheduledAt = empty($data['scheduled_at'])
            ? null
            : CarbonImmutable::parse((string) $data['scheduled_at'], $timezone)
                ->setTimezone((string) config('app.timezone', 'UTC'));

        $requiresApproval = (bool) ($data['requires_approval'] ?? false)
            || (bool) config('marketing.publishing.approval_required', true);

        return [
            'body' => $data['body'] ?? null,
            'media' => PostMedia::normalize((array) ($data['media'] ?? [])),
            'link' => $data['link'] ?? null,
            'hashtags' => $this->parseHashtags($data['hashtags'] ?? null),
            'scheduled_at' => $scheduledAt,
            'timezone' => $timezone,
            'requires_approval' => $requiresApproval,
            'status' => $scheduledAt === null ? PostStatus::Draft : PostStatus::Scheduled,
            'approved_at' => $requiresApproval
                ? $post?->approved_at
                : CarbonImmutable::now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    protected function accountIds(array $data): array
    {
        return array_values(array_map(
            static fn ($id): int => (int) $id,
            (array) ($data['account_ids'] ?? []),
        ));
    }

    /**
     * Add and remove deliveries without touching ones that already went out.
     *
     * @param  list<int>  $accountIds
     */
    protected function syncTargets(SocialPost $post, array $accountIds): void
    {
        $post->targets()
            ->whereNotIn('social_account_id', $accountIds)
            ->where('status', '!=', DeliveryStatus::Published->value)
            ->delete();

        $existing = $post->targets()->pluck('social_account_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        foreach (array_diff($accountIds, $existing) as $accountId) {
            $post->targets()->create(['social_account_id' => $accountId]);
        }
    }

    /**
     * @return list<string>
     */
    protected function parseHashtags(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $tags = array_map(
            static fn (string $tag): string => ltrim(trim($tag), '#'),
            preg_split('/[\s,]+/', $value) ?: [],
        );

        return array_values(array_filter($tags, static fn (string $tag): bool => $tag !== ''));
    }
}
