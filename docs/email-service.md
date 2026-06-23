# Email Service

## Overview

Multi-layered email system supporting SMTP configuration via admin UI, template-based emails with per-module overrides, queued/async delivery, and test/debug tools.

---

## Architecture

```
[Admin UI] → EmailSettingsController → EmailSettingsService → settings table (JSON, encrypted password)
                                                    ↕
[AppServiceProvider::boot()] → MailerConfigurator → overrides config/mail.php on every request
                                                    ↕
[Event handler] → MailService::send() → SendEmail job (queue: database)
    OR
[Test email]    → MailService::sendNow() → synchronous (Mail::html())
                                                    ↕
                                              Symfony Mailer (EsmtpTransport)
                                              → SocketStream → SMTP server
```

---

## Configuration (Admin UI)

All SMTP settings are stored in the `settings` table with key `email_settings` as a JSON blob.

| Field | Type | Description |
|-------|------|-------------|
| `mailer` | string | `smtp`, `sendmail`, `mail`, `log` |
| `smtp_host` | string | SMTP server hostname |
| `smtp_port` | int | Port (587 TLS, 465 SSL) |
| `smtp_encryption` | string | `tls`, `ssl`, `none` |
| `smtp_auth` | bool | Enable/disable authentication |
| `auto_tls` | bool | Automatically upgrade to STARTTLS |
| `smtp_allow_self_signed` | bool | Disable CA verification for self-signed certs |
| `smtp_ehlo_domain` | string | Custom EHLO/HELO hostname (empty = fallback to `app.url` host) |
| `smtp_username` | string | SMTP login username |
| `smtp_password` | string | Encrypted via `Crypt::encryptString()` on save |
| `from_email` | string | Global "From" address |
| `from_name` | string | Global "From" name |
| `reply_to_email` | string | Optional Reply-To header |
| `return_path` | string | Optional bounce handling address |

### Password Encryption

`EmailSettingsService::updateFromRequest()` encrypts the password before storage. `getOptions()` decrypts it on read using `try-decrypt` heuristic (trials `Crypt::decryptString()`, falls back to plain text).

---

## Key Files

### Backend

| File | Responsibility |
|------|---------------|
| `app/Services/MailerConfigurator.php` | Reads DB settings, overrides `config/mail.*` on every boot |
| `app/Services/MailService.php` | `send()` → queue, `sendNow()` → synchronous, `sendRaw()` |
| `app/Services/EmailSettingsService.php` | CRUD, encryption, sanitization, defaults |
| `app/Services/EmailTemplateService.php` | Renders `.blade.php` templates with global layout |
| `app/Http/Controllers/EmailSettingsController.php` | Save, test send, template preview, template send |
| `app/Jobs/SendEmail.php` | Queued job for async delivery |

### Notifications

| File | Responsibility |
|------|---------------|
| `app/Notifications/AppNotification.php` | Generic database notification |
| `app/Notifications/VerifyEmail.php` | Custom verify email (overrides Laravel default) |
| `app/Notifications/ResetPassword.php` | Custom password reset (overrides Laravel default) |
| `resources/views/vendor/notifications/email.blade.php` | Vendor notification template (branded) |

### Frontend

| File | Responsibility |
|------|---------------|
| `resources/js/Components/settings/sections/EmailSection.tsx` | SMTP settings form |
| `resources/js/Components/settings/sections/TemplateSimulationSection.tsx` | Template preview, test send, delivery log |

---

## Templates

24 templates in `resources/views/email/templates/`, rendered via `EmailTemplateService::renderWithLayout()` which:
1. Injects global data (`siteName`, `siteUrl`, `logoUrl`, `year`)
2. Renders the template-specific `.blade.php`
3. Wraps in `resources/views/email/layouts/email.blade.php`

### Core Templates

| Template | Group | Key Variables |
|----------|-------|---------------|
| `test` | System | `recipientName`, `recipientEmail` |
| `default` | System | `title`, `message` |
| `new-user` | Account | `userName`, `loginUrl` |
| `new-user-admin` | Account | `userName`, `userLogin`, `profileUrl` |
| `password-changed` | Account | `userName`, `resetUrl` |
| `password-reset` | Account | `userName`, `resetUrl`, `expiry` |
| `email-changed` | Account | `userName`, `oldEmail`, `newEmail` |
| `account-deleted` | Account | `userName` |
| `property-inquiry` | Properties | `inquiryName`, `inquiryEmail`, `inquiryMessage`, `propertyTitle` |

### Module Templates

Modules register templates via `getEmailTemplates()` in their module class. Each module template has its own sample data and prefix (e.g., `booking-*`, `order-*`).

---

## Data Flow

### Saving Settings

```
[Form submit] → router.put(route('settings.email.update')) → EmailSettingsController::update()
  → EmailSettingsService::updateFromRequest()
    → sanitize($data) — trim, validate ranges
    → Crypt::encryptString(smtp_password) if changed
    → Setting::updateOrCreate('email_settings')
    → Cache::forget('email_settings')
```

### Sending (Admin Test)

```
[Click "Send Test"] → router.post(route('settings.email.test'))
  → EmailSettingsController::sendTest()
    → MailService::sendNow(to, subject, 'test', sampleData)
      → EmailTemplateService::renderWithLayout('test', data)
      → Mail::html(html, callback)
      → returns true (throws on failure, caught by controller)
  → redirect→back with mail_debug flash
```

### Sending (Event-Driven)

```
[Event fires, e.g. onBookingCreated]
  → Service calls MailService::send()
    → SendEmail::dispatch(to, subject, template, data)  // queued
  → Returns immediately
  → Queue worker processes SendEmail job
    → MailService::sendNow() → sends via Symfony Mailer
```

---

## Delivery Log

Frontend maintains an in-memory delivery log (last 20 entries). Each entry captures:

- Template name
- Recipient
- Subject
- Mailer type
- SMTP host:port
- Encryption
- Username
- From name + address
- Success/failure
- Error message (if failed)

Clears on page reload.

---

## Known Pitfalls

### CA Bundle on Windows

PHP on Windows often lacks a CA certificate bundle (`openssl.cafile` unset). The **Allow Self-Signed** toggle sets `verify_peer => false` in the SSL stream context, bypassing CA verification. For production with valid certificates, set `openssl.cafile` in `php.ini`.

### EHLO/HELO Empty String

When `smtp_ehlo_domain` is empty in the DB, the null-coalescing operator (`??`) was used, which passes empty strings through. The fix uses `!empty()` to fall back to `parse_url(config('app.url'), PHP_URL_HOST)`.

### No .env Fallbacks

All `env()` calls have been removed from email defaults. The system relies entirely on DB-stored settings. `MailerConfigurator` falls back to `log` mailer if `smtp_host` is empty.

### Queue Worker Required

`MailService::send()` dispatches to the `database` queue. Emails will not be sent until `php artisan queue:work` runs on the server. `sendNow()` bypasses the queue (used for test emails).
