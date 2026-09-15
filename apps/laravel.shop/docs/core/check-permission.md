# CheckPermission Middleware

`app/Http/Middleware/CheckPermission.php`

Simple route middleware that checks a user has a specific permission slug.

## Registration

Registered in `bootstrap/app.php` as the `permission:` middleware alias.

## Behavior

```php
public function handle(Request $request, Closure $next, string $slug): Response
{
    if (! $request->user() || ! $request->user()->hasPermission($slug)) {
        abort(403);
    }
    return $next($request);
}
```

- Aborts with 403 if user is not authenticated
- Aborts with 403 if user lacks the permission
- Otherwise passes through

## Usage in Routes

Applied automatically by the `HasRoutes` trait when modules load routes with a permission parameter:

```php
$this->loadRoutes(__DIR__.'/Routes/web.php', '/dashboard/bookings', 'dashboard.bookings.', 'bookings.view');
```

This produces route groups with middleware: `['web', 'auth', 'verified', 'permission:bookings.view']`.
