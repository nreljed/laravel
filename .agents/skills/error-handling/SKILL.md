---
name: error-handling
description: "Laravel error handling, exception handling, and error pages for Cost Breaker 360. Activate when working with exceptions, error pages, try/catch, Handler, renderable exceptions, HTTP status codes, flash messages, or when user mentions error handling, exceptions, 404, 500, error pages, or debugging. Covers Inertia error pages, exception handler, flash messages, and logging."
license: MIT
metadata:
  author: laravel
---

# Error Handling

## Architecture

- **Exception Handler**: `app/Exceptions/Handler.php` (or `bootstrap/app.php` in Laravel 11+)
- **Error Pages**: `resources/js/Pages/Error.tsx` (Inertia-rendered for all HTTP exceptions)
- **Flash Messages**: Shared via `HandleInertiaRequests` middleware
- **Logging**: Laravel Pail, Telescope, Sentry (optional)

## Inertia Error Page

All HTTP exceptions render `Error.tsx` via Inertia:

```tsx
// resources/js/Pages/Error.tsx
import { Head } from "@inertiajs/react";

export default function Error({ status, message }: { status: number; message?: string }) {
  const title = {
    403: "Forbidden",
    404: "Not Found",
    419: "Page Expired",
    429: "Too Many Requests",
    500: "Server Error",
    503: "Service Unavailable",
  }[status] ?? "Error";

  return (
    <>
      <Head title={title} />
      <div className="flex min-h-screen items-center justify-center">
        <div className="text-center">
          <h1 className="text-4xl font-bold text-foreground">{status}</h1>
          <p className="text-muted-foreground mt-2">{message ?? title}</p>
        </div>
      </div>
    </>
  );
}
```

## Exception Handling (Laravel 13)

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    });

    $exceptions->report(function (Throwable $e) {
        // Custom reporting (e.g., Sentry)
    });
})
```

## Renderable Exceptions

Create custom exceptions with HTTP responses:

```php
class InsufficientBalanceException extends Exception implements Renderable
{
    public function render(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $this->getMessage()], 422);
        }
        return Inertia::render('Error', [
            'status' => 422,
            'message' => $this->getMessage(),
        ]);
    }
}
```

## HTTP Status Codes

| Code | Meaning | When to Use |
|---|---|---|
| 400 | Bad Request | Malformed input |
| 401 | Unauthorized | Not authenticated |
| 403 | Forbidden | Not authorized (permission denied) |
| 404 | Not Found | Resource doesn't exist |
| 419 | Page Expired | CSRF token mismatch |
| 422 | Unprocessable Entity | Validation failure |
| 429 | Too Many Requests | Rate limited |
| 500 | Server Error | Unhandled exception |
| 503 | Service Unavailable | Maintenance mode |

## Flash Messages

Shared via `HandleInertiaRequests`:

```php
// In controller
return redirect()->back()->with('success', 'Operation completed.');
return redirect()->back()->with('error', 'Something went wrong.');
return redirect()->back()->with('warning', 'Please review.');
return redirect()->back()->with('info', 'FYI.');
```

```tsx
// In React (shared props)
const { flash } = usePage().props;
// flash.success, flash.error, flash.warning, flash.info
```

## Logging

```php
// Log levels
Log::emergency('System down');
Log::alert('Action required');
Log::critical('Critical failure');
Log::error('Error occurred');
Log::warning('Warning');
Log::notice('Notice');
Log::info('Informational');
Log::debug('Debug info');

// Contextual logging
Log::error('Payment failed', [
    'user_id' => $user->id,
    'amount' => $amount,
    'error' => $e->getMessage(),
]);

// Channel-specific
Log::channel('slack')->info('Deploy completed');
```

## Validation Errors

FormRequest validation errors are automatically shared via Inertia:

```php
// FormRequest
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users'],
    ];
}

// In React
const { errors } = useForm({ ... });
// errors.name, errors.email (from server-side validation)
```

## Try/Catch Patterns

```php
// Controller
public function store(StoreRequest $request)
{
    try {
        $model = Model::create($request->validated());
        return redirect()->route('models.index')
            ->with('success', 'Created successfully.');
    } catch (\Exception $e) {
        Log::error('Failed to create model', ['error' => $e->getMessage()]);
        return redirect()->back()
            ->with('error', 'Failed to create. Please try again.')
            ->withInput();
    }
}
```

## Maintenance Mode

```php
// Check maintenance mode
php artisan down --secret="bypass-token"
php artisan up

// In middleware (CheckMaintenanceMode)
// Admins bypass maintenance mode
// Non-admins see 503 Blade template
```

## Error Boundaries (React)

```tsx
// Error boundary for component-level errors
class ErrorBoundary extends React.Component {
  state = { hasError: false };
  static getDerivedStateFromError() { return { hasError: true }; }
  render() {
    if (this.state.hasError) {
      return <div className="text-destructive">Something went wrong.</div>;
    }
    return this.props.children;
  }
}
```

## Key Files

| File | Purpose |
|---|---|
| `resources/js/Pages/Error.tsx` | Inertia error page (all HTTP errors) |
| `bootstrap/app.php` | Exception handler configuration |
| `resources/js/Components/ui/` | Error display components |
| `storage/logs/laravel.log` | Application log |

## Common Pitfalls

- **Swallowing exceptions**: Never use empty catch blocks. Always log or re-throw.
- **Generic error messages**: Provide specific, actionable error messages to users.
- **Missing `withInput()`**: When redirecting back on error, include `->withInput()` to preserve form data.
- **Flash message keys**: Use consistent keys (`success`, `error`, `warning`, `info`) across the app.
- **Inertia error handling**: Inertia renders `Error.tsx` for HTTP exceptions. Don't create separate error pages.
- **Validation vs. exceptions**: Use FormRequest for validation, exceptions for unexpected errors.
- **Production errors**: Never expose stack traces or sensitive data in production error responses.
- **Logging sensitive data**: Never log passwords, tokens, or PII.
