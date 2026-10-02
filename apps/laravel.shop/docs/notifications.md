# Notifications System

Zero-infrastructure design — works on any shared hosting with no long-running processes, no WebSocket servers, no third-party services.

## Architecture

```
User Action → NotificationService → notifications table (SQLite/MySQL)
                                         ↓
                                Frontend polls every 10s
                                via router.reload()
                                         ↓
                                NotificationBell badge updates
                                Toast for new notifications
```

All notification state is stored in Laravel's standard `notifications` database table. No cache, no queue, no broadcast.

## Backend

### NotificationService (`app/Services/NotificationService.php`)

Central dispatch. Checks user preferences before sending:

```
notifyViaPreferences($user, $type, $notification, $channels)
  ├─ Looks up NotificationPreference by user_id + type + channel
  │  (if no preference row exists, uses the type's default_enabled)
  ├─ Calls $user->notify($notification) for allowed channels
  │  (which triggers via() → ['database'] → toDatabase())
  └─ If 'email' is in allowed channels, sends a separate email via MailService
```

### Notification Preference Table

`notification_preferences` columns: `user_id`, `type`, `channel` (`in_app` | `email`), `enabled`.

Unique constraint: `(user_id, type, channel)`.

### AppNotification (`app/Notifications/AppNotification.php`)

Single notification class for all in-app notifications:

- `via()` returns `['database']` only
- `toDatabase()` stores `type`, `title`, `message`, `link`, etc.

### NotificationTypeRegistry

Central registry of all notification types. Each type defines:

| Field | Purpose |
|-------|---------|
| `label` | Human-readable name |
| `default_channels` | Default delivery channels (`['in_app']`) |
| `default_enabled` | Whether enabled by default |
| `category` | Grouping for UI (`booking`, `property`, `system`, `account`) |
| `email_template` | Optional Blade template path for email |

### Sharing Data to Inertia

`app/Http/Middleware/HandleInertiaRequests.php` shares two props on every request:

```
'latestNotifications'        → latest 5 notifications (eager loaded)
'notificationUnreadCount'    → COUNT WHERE read_at IS NULL
```

No caching — queries run fresh every request (the query is a single indexed COUNT on the user-scoped notifications table).

### Routes

| Method | URL | Controller Action | Purpose |
|--------|-----|------------------|---------|
| GET | `/dashboard/notifications` | `DashboardController@notifications` | Full list with search |
| PUT | `/dashboard/notifications/mark-read` | `DashboardController@markRead` | Mark one as read |
| POST | `/dashboard/notifications/mark-all-read` | `DashboardController@markAllRead` | Mark all as read |
| DELETE | `/dashboard/notifications/{id}` | `DashboardController@destroy` | Delete one |

## Frontend

### NotificationBell (`resources/js/Components/NotificationBell.tsx`)

Renders the bell icon in the sidebar header with the unread count badge. Wraps `useNotifications()` for the count. No polling logic here — it reads from Inertia shared props.

### useNotifications Hook (`resources/js/hooks/use-notifications.ts`)

```
Polling:
  └─ useEffect with setInterval(10000) → router.reload({ only: [...] })
        └─ Triggered by: mount
        └─ On each tick: refreshes latestNotifications + notificationUnreadCount

Optimistic updates:
  - markRead(id): updates local state immediately, PUTs to server
  - markAllRead(): clears all locally, POSTs to server
  - deleteNotification(id): removes locally, DELETEs to server

Sound:
  - Compares incoming unreadCount > previous count
  - Plays a short oscillator beep if sound is enabled
  - Sound toggle persisted in localStorage key 'notification_sound_enabled'
```

### Flash Consumer (`app.tsx` → InertiaFlashListener)

Listens for `router.on('navigate')` and reads `flash.status` / `flash.error` from props. Displays as Base UI toasts from `@/Components/ui/toast`. This catches flash messages from **any** navigation, not just notification actions.

### Notifications Page (`resources/js/Pages/Dashboard/Notifications.tsx`)

Full page at `/dashboard/notifications` with:

- **Search** — filters by `title`, `message`, `body`, `type` (server-side SQL LIKE)
- **Notification list** — each item shows type badge (color-coded), message preview, relative time
- **Detail dialog** — click a notification to see full message, link, metadata; mark read or delete from the dialog
- **Actions** — mark read / delete buttons always visible (no mobile-only hover)

### Search Filter

The `DashboardController::notifications()` method adds a `WHERE` clause:

```php
if ($search) {
    $query->where(function ($q) use ($search) {
        $q->where('data->title', 'like', "%{$search}%")
          ->orWhere('data->message', 'like', "%{$search}%")
          ->orWhere('data->body', 'like', "%{$search}%")
          ->orWhere('type', 'like', "%{$search}%");
    });
}
```

`data` is a JSON column — searching via `data->>'title'` would require `->>`, but Laravel's `where('data->title', ...)` uses the JSON extract syntax supported by both SQLite and MySQL.

## Notification Types Registry

Defined in a service provider. Example structure:

```php
$this->app->make(NotificationTypeRegistry::class)->register('booking.confirmed', [
    'label' => 'Booking Confirmed',
    'default_channels' => ['in_app', 'email'],
    'default_enabled' => true,
    'category' => 'booking',
    'email_template' => 'emails.notifications.booking-confirmed',
]);
```

## Sending a Notification

From anywhere in the app:

```php
$notification = new AppNotification('booking.confirmed', [
    'title' => 'Booking #123 Confirmed',
    'message' => 'Your booking has been confirmed.',
    'link' => route('dashboard.bookings.show', $booking),
]);

app(NotificationService::class)->notifyViaPreferences($user, 'booking.confirmed', $notification);
```

Or for a quick notification with default preferences:

```php
$user->notify(new AppNotification('booking.confirmed', [
    'title' => '...',
    'message' => '...',
]));
```

## No-Infrastructure Guarantees

| Feature | How it works | Infrastructure required |
|---------|-------------|----------------------|
| Deliver notification | INSERT into `notifications` table | None |
| Show unread count | SELECT COUNT with WHERE read_at IS NULL | None |
| Poll for updates | `setInterval` + `router.reload` | None |
| Sound on new notif | Web Audio API oscillator | None |
| Real-time push | ❌ Not available (would need WebSocket) | None |
| Email delivery | PHP `mail()` or SMTP via MailService | Standard PHP mail |
| Search notifications | SQL LIKE on JSON column | None |

The 10-second polling is the only active mechanism for detecting new notifications. On shared hosting with 100+ concurrent users, the polling requests are cheap (each is a lightweight Inertia partial reload from the user's own session).
