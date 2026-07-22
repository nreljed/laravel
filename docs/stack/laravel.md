# Laravel

## Version

| Package             | Version | Status   |
| ------------------- | ------- | -------- |
| `laravel/framework` | ^13.8   | Current  |
| PHP                 | ^8.3    | Required |

## Architecture

```
app/
  Http/
    Controllers/        # Organized by domain (Admin/, Api/, Auth/)
    Middleware/          # HandleInertiaRequests, Admin, Permission, Role, CSP
    Requests/           # FormRequest classes by domain
  Models/               # Eloquent models
  Services/             # Business logic (AccountService, DashboardService, etc.)
  Providers/            # AppServiceProvider, PermissionGateServiceProvider
  Traits/               # Shared traits (RelativeTime)
  Blog/                 # Self-contained feature module
```

## Key Patterns

### Service Layer

Business logic lives in `app/Services/`. Controllers delegate to services:

```php
// UserAccountController delegates to AccountService
app(AccountService::class)->deleteAccount($user);
```

### Feature Architecture

Features are self-contained domains registered via `FeatureRegistry`:

1. `FeatureRegistry` singleton delegates to feature service providers
2. Feature service providers register routes, permissions, nav items
3. `HandleInertiaRequests` calls `FeatureRegistry::getAllNavItems()` to share props

### Authorization

- `Gate::before()` grants admin full access
- Route middleware: `permission:feature.view`, `admin`, `role:slug`
- Controller level: `$this->authorize('action', Model::class)`
- FormRequest level: `$this->authorize()` in request class

### Validation

- FormRequest classes for complex validation
- Inline `$request->validate()` for simple cases
- Server-side only — no client-side validation duplication

### Error Handling

Inertia renders `Error.tsx` for all HTTP exceptions:

```php
$exceptions->renderable(function (HttpException $e, Request $request) {
    if ($request->inertia()) {
        return Inertia::render('Error', ['status' => $e->getStatusCode()])
            ->toResponse($request)
            ->setStatusCode($e->getStatusCode());
    }
});
```

## Conventions

- 4 spaces indentation
- PSR-12 style (enforced by Laravel Pint)
- Named routes where applicable
- Service classes are singletons when stateless
- Traits for shared behavior across unrelated classes
