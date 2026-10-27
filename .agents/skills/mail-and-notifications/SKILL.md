---
name: mail-and-notifications
description: "Laravel mail, Mailables, email templates, and notification dispatch for Cost Breaker 360. Activate when working with Mailable, Mail::to(), email templates, DynamicMailable, RecoveryMail, SendEmail job, EmailTemplateService, or when user mentions sending emails, email templates, mail configuration, or notification emails. Covers the DynamicMailable pattern, template rendering with layout, and email settings."
license: MIT
metadata:
  author: laravel
---

# Mail & Notifications (Email)

## Architecture

- **Mailables**: `Nreljed\Platform\Mail\` (DynamicMailable, RecoveryMail)
- **Job**: `Nreljed\Platform\Jobs\SendEmail` — queued email via `DynamicMailable`
- **Service**: `Nreljed\Platform\Services\EmailTemplateService` — renders templates with layout
- **Settings**: `EmailSettingsController` — admin email configuration
- **Templates**: Stored in DB, rendered with `EmailTemplateService::renderWithLayout()`

## Key Components

### DynamicMailable

```php
class DynamicMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected string $template,
        protected array $data = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->data['subject'] ?? 'Notification',
        );
    }

    public function content(): Content
    {
        $html = app(EmailTemplateService::class)->renderWithLayout($this->template, $this->data);

        return new Content(
            htmlString: $html,
        );
    }
}
```

### SendEmail Job (Queued)

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

### RecoveryMail

Password recovery email mailable.

## Sending Email

### Via Queue (Recommended)

```php
// Dispatch queued email
SendEmail::dispatch(
    to: $user->email,
    subject: 'Welcome to Cost Breaker 360',
    template: 'welcome',
    data: ['name' => $user->name, 'url' => route('dashboard')],
);
```

### Synchronously (Testing/Debug)

```php
Mail::to($user->email)->send(new DynamicMailable('welcome', [
    'subject' => 'Welcome',
    'name' => $user->name,
]));
```

### Direct Mailable

```php
Mail::to($user->email)->send(new RecoveryMail($user, $token));
```

## Email Templates

Templates are stored in the database and rendered via `EmailTemplateService`:

```php
// Render template with layout
$html = app(EmailTemplateService::class)->renderWithLayout($templateKey, $data);

// Template variables
$data = [
    'subject' => 'Email Subject',
    'name' => 'John Doe',
    'url' => route('dashboard'),
    'action_text' => 'View Dashboard',
];
```

## Email Settings

Admin-configurable via `EmailSettingsController`:

```php
// Get email settings
$settings = app(EmailSettingsController::class)->getSettings();

// Settings include: from address, from name, SMTP config, etc.
```

## Notification vs Email

| Feature | Database Notification | Email |
|---|---|---|
| Channel | Database | SMTP/Mail |
| Delivery | In-app (bell) | External (inbox) |
| Queued | Yes (via job) | Yes (SendEmail job) |
| Preferences | User notification prefs | Email settings (admin) |
| Templates | NotificationTypeRegistry | EmailTemplateService |

## Testing Mail

```php
use Illuminate\Support\Facades\Mail;

// Fake mail
Mail::fake();

// ... trigger code that sends email ...

// Assert mail was sent
Mail::assertSent(DynamicMailable::class, function ($mail) use ($user) {
    return $mail->hasTo($user->email);
});

// Assert mail was NOT sent
Mail::assertNotSent(DynamicMailable::class);

// Assert nothing was sent
Mail::assertNothingSent();

// Assert queued mail
Mail::assertQueued(DynamicMailable::class);
```

## Configuration

```php
// config/mail.php
'default' => env('MAIL_MAILER', 'smtp'),
'mailers' => [
    'smtp' => [
        'transport' => 'smtp',
        'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
        'port' => env('MAIL_PORT', 587),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
    ],
],
'from' => [
    'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
    'name' => env('MAIL_FROM_NAME', 'Example'),
],
```

## Key Files

| File | Purpose |
|---|---|
| `vendor/nreljed/platform/src/Jobs/SendEmail.php` | Queued email job |
| `vendor/nreljed/platform/src/Mail/DynamicMailable.php` | Dynamic template mailable |
| `vendor/nreljed/platform/src/Mail/RecoveryMail.php` | Password recovery email |
| `vendor/nreljed/platform/src/Services/EmailTemplateService.php` | Template rendering |
| `vendor/nreljed/platform/src/Services/MailService.php` | Mail service |
| `vendor/nreljed/platform/src/Http/Controllers/EmailSettingsController.php` | Admin email settings |
| `vendor/nreljed/platform/src/Http/Controllers/RecoveryController.php` | Password recovery |

## Common Pitfalls

- **Always queue emails**: Use `SendEmail::dispatch()` instead of `Mail::send()` directly in controllers.
- **Template not found**: Ensure the template key exists in the database before dispatching.
- **Subject override**: The `subject` in `$data` overrides the constructor subject. Pass it in data.
- **Testing**: Use `Mail::fake()` in tests. Never send real emails in tests.
- **After commit**: Set `'after_commit' => true` in queue config to ensure emails dispatch only after DB transaction commits.
