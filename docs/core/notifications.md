# Notification System

Comprehensive in-app notification system with database storage, adaptive polling, and preference-aware dispatch.

## Architecture

```
Notification Created
  → AppServiceProvider (creating hook auto-fills structured columns)
  → DB storage (notifications table)

Polling Cycle (every 10-60s):
  Frontend useNotifications hook
    → GET /api/notifications/recent (returns 5 latest + unread count)
    → If count increased: play sound + desktop notification
    → BroadcastChannel syncs count across tabs

Display:
  NotificationBell → NotificationPanel (slide-over sheet, 5 items)
  /dashboard/notifications → Full page with date grouping + batch ops
```

## Backend

### Notification Classes

| Class             | Channel    | Purpose                             |
| ----------------- | ---------- | ----------------------------------- |
| `AppNotification` | `database` | Main in-app notification class      |
| `VerifyEmail`     | `mail`     | Email verification (Laravel Breeze) |
| `ResetPassword`   | `mail`     | Password reset (Laravel Breeze)     |

`AppNotification` accepts a `type` string and optional `data` array. The `creating` hook in `AppServiceProvider` auto-populates `title`, `message`, `link`, `severity`, and `module` columns from the data array.

### NotificationService (`app/Services/NotificationService.php`)

| Method                                                                | Purpose                                          |
| --------------------------------------------------------------------- | ------------------------------------------------ |
| `getCachedUnreadCount($userId, $userType)`                            | Returns unread count with 5s cache TTL           |
| `invalidateUnreadCache($userId)`                                      | Clears cached count (called on mark-read/delete) |
| `notifyViaPreferences($notifiable, $type, $notification, ?$channels)` | Preference-aware dispatch (in_app + email)       |
| `purge($retentionDays = 90)`                                          | Hard-deletes old notifications                   |

### NotificationTypeRegistry (`app/Services/NotificationTypeRegistry.php`)

Features register notification types via `FeatureRegistry::getAllNotificationTypes()`. Each type defines:

```php
[
    'label' => 'Post Published',
    'group' => 'Content',
    'default_channels' => ['in_app'],
    'default_enabled' => true,
    'email_template' => null, // or blade template key
]
```

Currently registered types:

| Type             | Feature | Group   |
| ---------------- | ------- | ------- |
| `post.published` | Blog    | Content |
| `post.scheduled` | Blog    | Content |

### NotificationPreference Model

Stores per-user, per-type, per-channel opt-in/out preferences.

```
notification_preferences:
  id         BIGINT (PK)
  user_id    BIGINT (FK → users, cascade delete)
  type       VARCHAR (default: '')
  channel    VARCHAR (default: 'in_app')
  enabled    BOOLEAN (default: true)

  UNIQUE INDEX: (user_id, type, channel)
```

## Database Schema

### `notifications` table

```
id              UUID (PK)
type            VARCHAR (e.g., "App\\Notifications\\AppNotification")
notifiable_id   UNSIGNED BIGINT (polymorphic)
notifiable_type VARCHAR (polymorphic)
data            TEXT (JSON blob)
title           VARCHAR, nullable (auto-filled by creating hook)
message         TEXT, nullable (auto-filled by creating hook)
link            VARCHAR(500), nullable (auto-filled by creating hook)
severity        VARCHAR(20), default 'info' (auto-filled by creating hook)
module          VARCHAR(50), nullable (auto-filled by creating hook)
read_at         TIMESTAMP, nullable
created_at      TIMESTAMP
updated_at      TIMESTAMP

INDEX: notifications_unread_meta_index ON (notifiable_id, notifiable_type, read_at, created_at)
```

### Severity Levels

| Level      | Description      | Frontend Color |
| ---------- | ---------------- | -------------- |
| `info`     | Default          | Primary        |
| `warning`  | Attention needed | Amber          |
| `urgent`   | Time-sensitive   | Orange         |
| `critical` | Requires action  | Destructive    |

## API Endpoints

All under `auth` middleware + `throttle:120,1`.

| Method   | URL                                | Purpose                                   | Rate Limit |
| -------- | ---------------------------------- | ----------------------------------------- | ---------- |
| `GET`    | `/api/notifications/unread-count`  | Returns `{ count }`                       | 120/min    |
| `GET`    | `/api/notifications/recent`        | 5 latest + unread count                   | 120/min    |
| `GET`    | `/api/notifications/`              | Paginated list (limit/offset/type filter) | 120/min    |
| `POST`   | `/api/notifications/mark-read`     | Mark single as read                       | 120/min    |
| `POST`   | `/api/notifications/mark-all-read` | Mark all as read                          | 120/min    |
| `DELETE` | `/api/notifications/{id}`          | Delete single                             | 120/min    |
| `POST`   | `/api/notifications/mark-bulk`     | Mark multiple read (max 50 IDs)           | 20/min     |
| `POST`   | `/api/notifications/delete-bulk`   | Delete multiple (max 50 IDs)              | 20/min     |

### FormRequest Validation

- `MarkReadRequest`: `{ id: required|string }`
- `BulkNotificationRequest`: `{ ids: required|array|max:50, ids.*: required|string|uuid }`

### Page Routes

| Method   | URL                                         | Purpose                                         |
| -------- | ------------------------------------------- | ----------------------------------------------- |
| `GET`    | `/dashboard/notifications`                  | Full notifications page (server-side paginated) |
| `GET`    | `/dashboard/notifications/test`             | Admin test console                              |
| `POST`   | `/dashboard/notifications/test`             | Send test to self                               |
| `POST`   | `/dashboard/notifications/test/send-to-all` | Broadcast to all users                          |
| `DELETE` | `/dashboard/notifications/test/clear`       | Clear own notifications                         |

## Frontend

### `useNotifications` Hook (`resources/js/hooks/use-notifications.ts`)

Central state management for notifications. Used by `NotificationBell` in the layout.

**Adaptive Polling:**

| State         | Interval                | Trigger                                 |
| ------------- | ----------------------- | --------------------------------------- |
| Active        | 10s                     | User interaction within 2 min           |
| Idle          | 30s                     | No interaction 2-5 min                  |
| Standby       | 60s                     | No interaction 5+ min                   |
| Error backoff | 60s × 2^(n-3), max 5min | After 3 consecutive errors              |
| Tab hidden    | Paused                  | `document.visibilityState === 'hidden'` |

**Features:**

- Single HTTP request per poll cycle (fetches `/recent` which returns count + notifications)
- `BroadcastChannel("app-notifications")` for cross-tab sync
- Sound notification (Web Audio API, 520Hz sine wave)
- Browser desktop notifications (when tab hidden + permission granted)
- Optimistic UI updates for mark-read, mark-all-read, delete
- Stale response cancellation via generation counter

### Components

| Component                  | File                                                    | Purpose                                          |
| -------------------------- | ------------------------------------------------------- | ------------------------------------------------ |
| `NotificationBell`         | `Components/notifications/NotificationBell.tsx`         | Bell icon + badge in header, opens panel         |
| `NotificationPanel`        | `Components/notifications/NotificationPanel.tsx`        | Slide-over sheet with 5 recent notifications     |
| `NotificationItem`         | `Components/notifications/NotificationItem.tsx`         | Memoized list item with type icon + severity dot |
| `NotificationDetailDialog` | `Components/notifications/NotificationDetailDialog.tsx` | Full detail modal                                |

### Shared Utils (`resources/js/lib/notifications.ts`)

| Export                            | Purpose                                                                                        |
| --------------------------------- | ---------------------------------------------------------------------------------------------- |
| `normalizeNotificationType(type)` | Converts FQCN to dot notation (e.g., `App\Notifications\AppNotification` → `app.notification`) |
| `getSeverity(type, notification)` | Infers severity from type keywords or uses explicit `severity` field                           |
| `getTypeLabel(type)`              | Human-readable label for notification type                                                     |
| `Notification` interface          | TypeScript type for notification objects                                                       |
| `Severity` type                   | `"info" \| "warning" \| "urgent" \| "critical"`                                                |

### Notifications Page (`Pages/dashboard/notifications/index.tsx`)

Full-featured notification center:

- Server-side pagination (20 per page) with "Load more"
- Client-side filtering: read/unread tabs, type tabs (All/Account/Content/Orders)
- Client-side search
- Date grouping: Today, Yesterday, This Week, Last Week, Older (accordion)
- Batch operations: multi-select, batch mark-read, batch delete

## Shared Props (HandleInertiaRequests)

Every authenticated page load includes:

```php
'latestNotifications' => fn () => $user->notifications()->latest()->take(5)->get()->map(...)
'notificationUnreadCount' => fn () => NotificationService::getCachedUnreadCount(...)
```

## Permissions

| Slug                   | Description                     |
| ---------------------- | ------------------------------- |
| `view-notifications`   | View notifications              |
| `manage-notifications` | Mark read, delete notifications |

## Scheduled Tasks

```php
// routes/console.php
Schedule::command('notifications:purge', ['--days' => 90])->daily();
```

Deletes notifications older than 90 days. Also available as `php artisan notifications:purge --days=N`.

## How to Add Notification Types

1. Define the type in your feature's service provider:

```php
// In FeatureRegistry::getAllNotificationTypes()
public function getAllNotificationTypes(): array
{
    return [
        'order.placed' => [
            'label' => 'Order Placed',
            'group' => 'Orders',
            'default_channels' => ['in_app', 'email'],
            'email_template' => 'emails.order-placed',
        ],
    ];
}
```

2. Dispatch the notification:

```php
use App\Notifications\AppNotification;

$user->notify(new AppNotification('order.placed', [
    'title' => 'New order received',
    'message' => "Order #{$order->id} from {$order->customer_name}",
    'link' => route('dashboard.orders.show', $order->id),
    'severity' => 'info',
    'module' => 'orders',
]));
```

3. The `AppServiceProvider` creating hook auto-populates structured columns from the data array.

## File Reference

| File                                                                 | Purpose                              |
| -------------------------------------------------------------------- | ------------------------------------ |
| `app/Notifications/AppNotification.php`                              | Database notification class          |
| `app/Services/NotificationService.php`                               | Dispatch + cache + purge             |
| `app/Services/NotificationTypeRegistry.php`                          | Type definitions from features       |
| `app/Models/Notification.php`                                        | Extends Laravel DatabaseNotification |
| `app/Models/NotificationPreference.php`                              | Per-user channel preferences         |
| `app/Http/Controllers/Api/NotificationPollController.php`            | JSON API endpoints                   |
| `app/Http/Controllers/NotificationTestController.php`                | Admin test console                   |
| `app/Http/Requests/Api/MarkReadRequest.php`                          | Single mark-read validation          |
| `app/Http/Requests/Api/BulkNotificationRequest.php`                  | Bulk operations validation           |
| `app/Console/Commands/PurgeNotifications.php`                        | Artisan purge command                |
| `resources/js/hooks/use-notifications.ts`                            | Frontend polling + state             |
| `resources/js/lib/notifications.ts`                                  | Shared TypeScript utils              |
| `resources/js/Components/notifications/NotificationBell.tsx`         | Header bell + badge                  |
| `resources/js/Components/notifications/NotificationPanel.tsx`        | Slide-over panel                     |
| `resources/js/Components/notifications/NotificationItem.tsx`         | List item component                  |
| `resources/js/Components/notifications/NotificationDetailDialog.tsx` | Detail dialog                        |
| `resources/js/Pages/dashboard/notifications/index.tsx`               | Full notifications page              |
| `resources/js/Pages/dashboard/notifications/test.tsx`                | Admin test page                      |
