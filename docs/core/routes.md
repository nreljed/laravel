# Route Architecture

## Route Files

| File              | Prefix | Middleware | Purpose                                                 |
| ----------------- | ------ | ---------- | ------------------------------------------------------- |
| `routes/web.php`  | `/`    | web        | Dashboard, admin, public, deploy, switch-mode           |
| `routes/auth.php` | `/`    | web        | Breeze auth + profile, loaded by `RouteServiceProvider` |

## Feature Route Registration

Feature routes are registered in each feature's service provider (e.g., `BlogServiceProvider`). Routes are loaded directly in the service provider's `boot()` method:

```php
public function boot(): void
{
    $this->loadRoutesFrom(__DIR__ . '/Routes/web.php');
}
```

### Route Middleware

- **Authenticated dashboard routes**: `web`, `auth`, `verified`, `permission:{slug}`
- **Admin routes**: `web`, `auth`, `verified`, `admin`, `permission:{slug}`
- **Public guest routes**: `web` only

## Route Naming

Routes follow the convention: `{prefix}.{name}` where name is defined in the route file.

Examples:

- `dashboard.posts.index`
- `dashboard.posts.create`
- `dashboard.posts.edit`

## Route Listing

```bash
php artisan route:list --path=posts
```

## Auth Routes

Uses named rate limiters from `AppServiceProvider::configureRateLimiting()`.

| Method | URL                            | Middleware             | Limiter         | Purpose              |
| ------ | ------------------------------ | ---------------------- | --------------- | -------------------- |
| `GET`  | `/register`                    | `guest`, `maintenance` | —               | Registration form    |
| `POST` | `/register`                    | `guest`, `maintenance` | `register`      | Create account       |
| `GET`  | `/login`                       | `guest`, `maintenance` | —               | Login form           |
| `POST` | `/login`                       | `guest`, `maintenance` | `login`         | Authenticate         |
| `POST` | `/forgot-password`             | `guest`, `maintenance` | `auth`          | Send reset link      |
| `POST` | `/reset-password`              | `guest`, `maintenance` | `auth`          | Reset password       |
| `POST` | `/confirm-password`            | `auth`, `maintenance`  | `auth`          | Confirm password     |
| `PUT`  | `/password`                    | `auth`, `maintenance`  | `auth`          | Update password      |
| `POST` | `/logout`                      | `auth`, `maintenance`  | `auth`          | Destroy session      |
| `GET`  | `/register/check-availability` | —                      | `throttle:10,1` | Username/email check |

**Rate limiter details:**

| Limiter    | Limit  | Key | Notes                                                    |
| ---------- | ------ | --- | -------------------------------------------------------- |
| `login`    | 30/min | IP  | Safety net; `LoginRequest` handles 5/min per username+IP |
| `register` | 10/min | IP  | Prevents bulk registration abuse                         |
| `auth`     | 30/min | IP  | General auth routes                                      |

See [Architecture](architecture.md#rate-limiting) for the two-tier login throttle explanation.

## Notification Routes

| Method   | URL                                         | Middleware               | Purpose                     |
| -------- | ------------------------------------------- | ------------------------ | --------------------------- |
| `GET`    | `/dashboard/notifications`                  | `auth`                   | Full notifications page     |
| `GET`    | `/api/notifications/unread-count`           | `auth`, `throttle:120,1` | Unread count JSON           |
| `GET`    | `/api/notifications/recent`                 | `auth`, `throttle:120,1` | 5 latest notifications JSON |
| `GET`    | `/api/notifications/`                       | `auth`, `throttle:120,1` | Paginated list JSON         |
| `POST`   | `/api/notifications/mark-read`              | `auth`, `throttle:120,1` | Mark single read            |
| `POST`   | `/api/notifications/mark-all-read`          | `auth`, `throttle:120,1` | Mark all read               |
| `DELETE` | `/api/notifications/{id}`                   | `auth`, `throttle:120,1` | Delete single               |
| `POST`   | `/api/notifications/mark-bulk`              | `auth`, `throttle:20,1`  | Bulk mark read              |
| `POST`   | `/api/notifications/delete-bulk`            | `auth`, `throttle:20,1`  | Bulk delete                 |
| `GET`    | `/dashboard/notifications/test`             | `auth`, `admin`          | Test console                |
| `POST`   | `/dashboard/notifications/test`             | `auth`, `admin`          | Send test to self           |
| `POST`   | `/dashboard/notifications/test/send-to-all` | `auth`, `admin`          | Broadcast to all            |
| `DELETE` | `/dashboard/notifications/test/clear`       | `auth`, `admin`          | Clear own notifications     |

See [Notifications](notifications.md) for full architecture.
