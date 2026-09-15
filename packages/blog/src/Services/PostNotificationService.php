<?php

namespace Nreljed\Blog\Services;

use Nreljed\Blog\Models\Post;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Notifications\AppNotification;
use Nreljed\Platform\Services\NotificationService;

/**
 * Emits the `post.published` / `post.scheduled` notification types when a post
 * changes status. Mirrors the content-lifecycle notifications the WordPress
 * plugin ported into this feature used to dispatch on post transitions.
 */
class PostNotificationService
{
    public function __construct(protected NotificationService $notificationService) {}

    /**
     * Dispatch the relevant notification for a status transition. A transition
     * into `published` notifies the author; entering the `future` status (the
     * scheduled state) notifies the author that the post is scheduled.
     */
    public function dispatch(Post $post, ?string $oldStatus): void
    {
        $newStatus = $post->status;
        $user = $post->user;

        if ($user === null) {
            return;
        }

        if ($oldStatus !== 'published' && $newStatus === 'published') {
            $this->notify($post, $user, 'post.published', false);
        }

        if ($newStatus === 'future') {
            $this->notify($post, $user, 'post.scheduled', true);
        }
    }

    /**
     * Build the notification payload (database + email) and dispatch it through
     * the preference-aware pipeline.
     */
    protected function notify(Post $post, User $user, string $type, bool $scheduled): void
    {
        $verb = $scheduled ? 'is scheduled for publication' : 'has been published.';
        $prefix = $scheduled ? 'Post Scheduled' : 'Post Published';
        $editUrl = route('dashboard.posts.edit', ['post' => $post->id], false);

        $payload = [
            'title' => $prefix.': '.$post->title,
            'message' => 'Your post "'.$post->title.'" '.$verb,
            'link' => $editUrl,
            'module' => 'posts',
            'severity' => 'info',
            'context' => [
                'post_id' => $post->id,
                'scheduled' => $scheduled ? $post->published_at?->toDateTimeString() : null,
            ],
            'postTitle' => $post->title,
            'postUrl' => $scheduled || $post->status !== 'published'
                ? $editUrl
                : route('blog.show', ['slug' => $post->slug]),
            'userName' => $user->display_name ?: $user->name,
        ];

        if ($scheduled) {
            $payload['scheduledAt'] = $post->published_at?->toDateTimeString();
        }

        $this->notificationService->notifyViaPreferences(
            $user,
            $type,
            new AppNotification($type, $payload),
        );
    }
}
