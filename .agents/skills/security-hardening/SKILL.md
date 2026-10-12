---
name: security-hardening
description: "Security patterns and hardening for Cost Breaker 360. Activate when working with authentication, authorization, CSRF, CSP, rate limiting, session security, input validation, or when user mentions security, hardening, vulnerabilities, or protection. Covers middleware, CSP headers, CSRF exemptions, rate limiting, and auth flows."
license: MIT
metadata:
  author: laravel
---

# Security Hardening

## Middleware Stack

| Middleware                 | Purpose                            |
| -------------------------- | ---------------------------------- |
| `AdminMiddleware`          | 403 if not admin role              |
| `CheckPermission`          | 403 if user lacks permission slug  |
| `CheckRole`                | 403 if user lacks role slug        |
| `CheckIpAccess`            | IP-based access control            |
| `ContentSecurityPolicy`    | CSP headers (production only)      |
| `CheckMaintenanceMode`     | 503 Blade template (admins bypass) |
| `ConfigureSessionLifetime` | DB-driven session timeout          |

Order in `bootstrap/app.php` (web group append): `ConfigureSessionLifetime` → `CheckIpAccess` → `ContentSecurityPolicy` → `HandleInertiaRequests` → `AddLinkHeadersForPreloadedAssets`.

## Content Security Policy

```php
// vendor/nreljed/platform/src/Http/Middleware/ContentSecurityPolicy.php
// Only applies in production — nonce-based, not 'unsafe-inline'
// Generates a per-request nonce and applies it via Vite::useCspNonce($nonce)
// Directives: default-src, script-src, style-src, font-src, img-src, connect-src, worker-src, frame-src, object-src, base-uri, form-action, report-uri
```

Policy includes:

- `default-src 'self'`
- `script-src 'self' 'nonce-{nonce}'` (nonce supplied to Vite)
- `style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net`
- `font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net data:`
- `img-src 'self' data: blob: https:`
- `connect-src 'self' {wss|ws}://appUrl` (WebSocket variant of `APP_URL`)
- `worker-src 'self' blob:`
- `frame-src 'none'`
- `object-src 'none'`
- `base-uri 'self'`
- `form-action 'self'`
- `report-uri /csp-report`

## Rate Limiting

Named rate limiters defined in `AppServiceProvider::configureRateLimiting()`:

| Limiter    | Limit  | Key | Purpose                                                   |
| ---------- | ------ | --- | --------------------------------------------------------- |
| `login`    | 30/min | IP  | Safety net — `LoginRequest` handles strict per-user limit |
| `register` | 10/min | IP  | Registration abuse prevention                             |
| `auth`     | 30/min | IP  | General auth routes (password reset, logout, etc.)        |
| Admin      | 60/min | IP  | Admin panel routes                                        |
| Deploy     | 5/60s  | —   | Token-protected deploy endpoints                          |

**Login two-tier system:**

1. Route-level `throttle:login` — IP-based, 30/min
2. `LoginRequest::ensureIsNotRateLimited()` — username+IP based, configurable via `max_login_attempts` (default 5), returns `ValidationException` with wait-time message

## CSRF Exemptions

Narrow patterns only:

- `profile/avatar` (file upload)
- `deploy/*` (token-protected)
- Specific `api/notifications/*` paths

## Auth Patterns

### Password Policy

```php
// vendor/nreljed/platform/src/Rules/MinPasswordLength.php
// Reads password_min_length from DB — configurable per deployment
```

### Max Login Attempts

```php
// LoginRequest::ensureIsNotRateLimited()
// Reads max_login_attempts from DB
```

### Session Lifetime

```php
// vendor/nreljed/platform/src/Http/Middleware/ConfigureSessionLifetime.php
// DB-driven session timeout — not config-based
```

### Email Verification

- All dashboard routes require `verified` middleware
- Breeze handles verification flow

## Authorization Patterns

### Triple-Layer Check

1. **Route middleware**: `permission:posts.view` or `admin`
2. **Controller**: `$this->authorize('posts.view')`
3. **FormRequest**: `authorize()` method

### Impersonation

- Admin can impersonate users
- `impersonating` flag in shared props
- Limited to admin role only

## Input Validation

- FormRequest classes for complex validation
- `$request->validate()` for simple cases
- DB-driven rules (password length, login attempts)
- Zod schemas for frontend validation

## Key Rules

- Never expose secrets or keys in logs
- CSP uses a per-request nonce (`Vite::useCspNonce`) and only applies in production
- CSRF exemptions are narrow patterns, not blanket
- Token-protected deploy endpoints (not session auth)
- All auth routes are rate-limited
- Session lifetime is DB-driven (not config)
- Password policy is DB-driven (not config)
