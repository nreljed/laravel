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
