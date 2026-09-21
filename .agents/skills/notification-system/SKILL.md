---
name: notification-system
description: "Laravel database notification system for Cost Breaker 360. Activate when working with notifications, notification polling, notification preferences, notification bell, or when user mentions notifications, alerts, or real-time updates. Covers the NotificationService, preference-aware dispatch, adaptive polling, and notification types."
license: MIT
metadata:
  author: laravel
---

# Notification System

## Architecture

- **Channel**: Database (not mail, not broadcast)
- **Service**: `App\Services\NotificationService` — cached unread count, preference-aware dispatch
- **Registry**: `App\Services\NotificationTypeRegistry` — type configs aggregated from feature providers via `FeatureRegistry::getAllNotificationTypes()`
- **Polling**: Adaptive (10s → 30s → 60s) via `use-notifications.ts`
- **UI**: `NotificationBell.tsx` + `NotificationPanel.tsx`

## Key Components

### NotificationService

```php
// Cached unread count (5-min TTL)
$service->getCachedUnreadCount($userId, $userType);

// Invalidate cache after read/delete
$service->invalidateUnreadCache($userId);

// Preference-aware dispatch
$service->notifyViaPreferences($notifiable, 'post.published', $notification);

// Purge old notifications (90-day default)
$service->purge($retentionDays = 90);
```

### Notification Types (via FeatureServiceProvider)

Feature service providers implement `getNotificationTypes()`. `NotificationTypeRegistry` (via `FeatureRegistry::getAllNotificationTypes()`) aggregates them — providers: `BlogServiceProvider`.

```php
// In BlogServiceProvider::getNotificationTypes()
public function getNotificationTypes(): array
{
    return [
        'post.published' => [
            'label' => 'Post Published',
            'description' => 'When a post is published.',
            'default_channels' => ['in_app'],
            'default_enabled' => true,
            'group' => 'Content',
        ],
    ];
}
```

### Notification Preference Model

```php
// App\Models\NotificationPreference
// Columns: user_id, type, channel, enabled
NotificationPreference::where('user_id', $userId)
    ->where('type', $type)
    ->whereIn('channel', $channels)
    ->get();
```

## Frontend Polling

### use-notifications.ts

- Native `fetch()` (not axios, not Inertia `useHttp`) — standalone HTTP with `X-Requested-With` + `X-CSRF-TOKEN` headers from `@/lib/gallery-utils`
- Adaptive intervals: 10s (active) → 30s (idle) → 60s (standby), with exponential backoff up to 5 min after 3 consecutive errors
- Idle thresholds: 2 min → 30s polling, 5 min → 60s polling
- Endpoints (Ziggy names): `GET api.notifications` (list, limit/offset), `GET api.notifications.recent`, `POST api.notifications.mark-read`, `POST api.notifications.mark-all-read`, `DELETE api.notifications.destroy`
- Extras: `BroadcastChannel` ("app-notifications") cross-tab sync, notification sound, desktop `Notification` on hidden tab, visibility-based pause/resume, refresh on `router.on("finish")`

### NotificationBell.tsx

- Shows unread count badge
- Opens NotificationPanel on click
- Auto-polls when component mounts

## Dispatching Notifications

```php
use App\Services\NotificationService;

$notificationService->notifyViaPreferences(
    $user,
    'post.published',
    new PostPublishedNotification($post)
);
```

## Key Rules

- Always use `notifyViaPreferences()` — not `$user->notify()` directly
- Invalidate cache after marking read or deleting
- Feature notification types must be registered in ServiceProvider
- Email channel requires `email_template` in type config
- Notifications are purged after 90 days by default
