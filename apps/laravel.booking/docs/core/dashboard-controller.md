# DashboardController

`app/Http/Controllers/DashboardController.php`

Handles dashboard pages, notifications CRUD, and host/guest mode switching.

## Methods

### `index()` — `GET /dashboard`

Renders `Dashboard/Index` with stats from `DashboardService::getStats()`.

### `stats()` — `GET /dashboard/stats`

Renders `Dashboard/Stats`.

### `notifications()` — `GET /dashboard/notifications`

Paginated (20 per page) notifications list. Supports filters:

- `filter`: `all`, `unread`, `read`
- `type_filter`: `booking`, `property`, `account`, `content`, `orders`

### `markRead()` — `POST /dashboard/notifications/{id}/read`

Marks a single notification as read.

### `markAllRead()` — `POST /dashboard/notifications/read-all`

Marks all unread notifications as read.

### `destroyNotification()` — `DELETE /dashboard/notifications/{id}`

Deletes a single notification.

### `markBulk()` — `POST /dashboard/notifications/bulk-read`

Bulk mark read by array of notification IDs.

### `deleteBulk()` — `POST /dashboard/notifications/bulk-delete`

Bulk delete by array of notification IDs.

### `switchMode()` — `POST /dashboard/user/mode`

Toggles the host/guest mode in session. Validates `mode` is `host` or `guest`. Requires `bookings.view` permission (aborts 403 if missing).

## Notification Type Filter Patterns

| Filter | Matches |
|--------|---------|
| `booking` | `booking.%`, `%booking%` |
| `property` | `property.%`, `%property%` |
| `account` | `%user%`, `%password%`, `%email%`, `%account%` |
| `content` | `post.%`, `%content%` |
| `orders` | `order.%`, `%stock%`, `%shop%` |

## Routes Applied

All routes in `routes/web.php` under the `auth` middleware group.
