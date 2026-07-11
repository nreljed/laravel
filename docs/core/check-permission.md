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

Applied via route group middleware arrays:

```php
Route::middleware(['auth', 'verified', 'permission:posts.view'])->group(function () {
    Route::get('/dashboard/posts', [PostController::class, 'index'])->name('dashboard.posts.index');
});
```

This produces route groups with middleware: `['web', 'auth', 'verified', 'permission:posts.view']`.
