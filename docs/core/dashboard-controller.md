# DashboardController

`app/Http/Controllers/DashboardController.php`

Handles dashboard pages and host/guest mode switching.

## Methods

### `index()` — `GET /dashboard`

Renders `Dashboard/Index` with stats from `DashboardService::getStats()`.

### `stats()` — `GET /dashboard/stats`

Renders `Dashboard/Stats`.

### `notifications()` — `GET /dashboard/notifications`

Paginated (20 per page) notifications list. Supports filters:

- `filter`: `all`, `unread`, `read`
- `type_filter`: `account`, `content`, `orders`

### `switchMode()` — `POST /dashboard/user/mode`

Toggles the host/guest mode in session. Validates `mode` is `host` or `guest`. Requires `bookings.view` permission (aborts 403 if missing).

## Notification CRUD

Notification CRUD is handled by `NotificationPollController` at `/api/notifications/*` — see [Notifications](notifications.md).

## Routes Applied

All routes in `routes/web.php` under the `auth` middleware group.
