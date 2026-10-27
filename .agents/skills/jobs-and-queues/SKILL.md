---
name: jobs-and-queues
description: "Laravel queues, jobs, dispatching, and background processing for Cost Breaker 360. Activate when working with ShouldQueue, Queueable, dispatch(), job classes, queue workers, failed jobs, retry/backoff, or when user mentions background tasks, async processing, queued jobs, or queue configuration. Covers the SendEmail job, InvokeAiTask, DispatchFormSubmissionNotification, queue configuration, and job testing patterns."
license: MIT
metadata:
  author: laravel
---

# Jobs & Queues

## Architecture

- **Queue driver**: Database (default) / Redis (production)
- **Job classes**: `Nreljed\Platform\Jobs\` and `Nreljed\Blog\Jobs\`
- **Dispatch**: `dispatch()`, `dispatchSync()`, `Bus::dispatch()`
- **Workers**: `php artisan queue:work` (dev), Supervisor (prod)
- **Failed jobs**: `failed_jobs` table, `php artisan queue:failed`

## Key Jobs

### SendEmail (`Nreljed\Platform\Jobs\SendEmail`)

Queued email via `DynamicMailable` + `EmailTemplateService`:

```php
class SendEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        protected string $to,
        protected string $subject,
        protected string $template,
        protected array $data = [],
    ) {}

    public function handle(): void
    {
        $data = array_merge($this->data, ['subject' => $this->subject]);
        Mail::to($this->to)->send(new DynamicMailable($this->template, $data));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[SendEmail] Job failed permanently for: ' . $this->to);
    }
}
```

### InvokeAiTask (`Nreljed\Platform\Ai\Jobs\InvokeAiTask`)

Queued AI task execution via `AiTaskRunner`.

### DispatchFormSubmissionNotification (`Nreljed\Platform\Jobs\DispatchFormSubmissionNotification`)

Fan-out notification on form submission.

### GenerateSeoForPost (`Nreljed\Blog\Jobs\GenerateSeoForPost`)

Queued SEO generation for blog posts.

## Dispatching

```php
// Dispatch to queue
SendEmail::dispatch($to, $subject, $template, $data);

// Dispatch synchronously (for testing)
SendEmail::dispatchSync($to, $subject, $template, $data);

// Delayed dispatch
SendEmail::dispatch($to, $subject, $template, $data)->delay(now()->addMinutes(5));

// Chain jobs
Bus::chain([
    new GenerateSeoForPost($post),
    new DispatchFormSubmissionNotification($submission),
])->dispatch();

// Batch jobs
Bus::batch([
    new SendEmail(...),
    new SendEmail(...),
])->dispatch();
```

## Job Properties

| Property | Purpose | Example |
|---|---|---|
| `$tries` | Max retry attempts | `3` |
| `$backoff` | Seconds between retries | `60` |
| `$timeout` | Max execution time (seconds) | `120` |
| `$maxExceptions` | Max unhandled exceptions before fail | `3` |
| `$deleteWhenMissingModels` | Auto-delete if model missing | `true` |

## Queue Configuration

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'database'),
'connections' => [
    'database' => [
        'driver' => 'database',
        'table' => 'jobs',
        'queue' => 'default',
        'retry_after' => 90,
        'after_commit' => true,
    ],
],
```

## Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry a specific failed job
php artisan queue:retry {uuid}

# Retry all failed jobs
php artisan queue:retry all

# Flush failed jobs
php artisan queue:flush

# Forget a specific failed job
php artisan queue:forget {uuid}
```

## Worker Commands

```bash
# Start worker (dev)
php artisan queue:work

# Start worker with specific queue
php artisan queue:work --queue=emails,notifications,default

# Start worker with timeout
php artisan queue:work --timeout=120

# Listen for new jobs (auto-restart on code change)
php artisan queue:listen

# Restart workers (after deploy)
php artisan queue:restart
```

## Testing Jobs

```php
use Illuminate\Support\Facades\Queue;

// Assert job was dispatched
Queue::fake();
SendEmail::dispatch($to, $subject, $template, $data);
Queue::assertPushed(SendEmail::class);
Queue::assertPushed(SendEmail::class, function ($job) use ($to) {
    return $job->to === $to;
});

// Assert job was NOT dispatched
Queue::assertNotPushed(SendEmail::class);

// Assert nothing was pushed
Queue::assertNothingPushed();

// Test job execution synchronously
Queue::fake();
// ... trigger code that dispatches ...
Queue::assertPushed(SendEmail::class);
```

## Patterns

### ShouldBeUnique

Prevent duplicate jobs:

```php
class GenerateSeoForPost implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return $this->post->id;
    }
}
```

### Job Middleware

```php
public function middleware(): array
{
    return [new RateLimited('emails')];
}
```

### Chaining with Closures

```php
Bus::chain([
    function () { /* step 1 */ },
    new ProcessPayment($order),
    function () { /* step 3 */ },
])->catch(function (Throwable $e) {
    // Handle chain failure
})->dispatch();
```

## Key Files

| File | Purpose |
|---|---|
| `vendor/nreljed/platform/src/Jobs/SendEmail.php` | Queued email job |
| `vendor/nreljed/platform/src/Jobs/DispatchFormSubmissionNotification.php` | Form submission fan-out |
| `vendor/nreljed/platform/src/Ai/Jobs/InvokeAiTask.php` | Queued AI task |
| `vendor/nreljed/blog/src/Jobs/GenerateSeoForPost.php` | Queued SEO generation |
| `config/queue.php` | Queue configuration |
| `database/migrations/*_create_jobs_table.php` | Jobs table migration |

## Common Pitfalls

- **Missing `after_commit`**: Set `'after_commit' => true` in queue config to ensure jobs dispatch only after DB transaction commits.
- **Serialization**: Jobs must be serializable. Use `SerializesModels` trait for Eloquent models. Don't pass closures to queued jobs.
- **Memory leaks**: Long-running workers accumulate memory. Use `--max-jobs=1000` or `--max-time=3600` flags.
- **Stale code**: After deploy, run `php artisan queue:restart` to pick up new code.
- **Testing**: Always use `Queue::fake()` in tests. Never run real queue workers in tests.
