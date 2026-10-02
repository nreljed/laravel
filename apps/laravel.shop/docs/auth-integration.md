# Auth Integration

## Overview

Laravel Breeze (Inertia + React) with custom modifications for dual login (username/email), session-based auth via Sanctum, and WordPress-style display name system.

---

## Architecture

```
[Browser] → Inertia form submit → [Laravel Route] → [FormRequest] → [Controller] → Response (redirect + flash)
                     ↕                               ↕
              Zod client validation          Server validation
```

## Form Field Naming

| Page | Form Fields | Server Expects | Column |
|------|------------|----------------|--------|
| Login | `username`, `password`, `remember` | `username`, `password`, `remember` | `email` or `username` (auto-detected) |
| Register | `username`, `email`, `password`, `password_confirmation` | `username`, `email`, `password`, `password_confirmation` | all columns |
| Forgot Password | `email` | `email` | `email` |
| Reset Password | `password`, `password_confirmation` | `token`, `email`, `password`, `password_confirmation` | `password` |
| Confirm Password | `password` | `password` | `password` |
| Verify Email | — (resend button) | — | — |

## Login Flow

```
1. User submits username/email + password
2. Inertia useForm sends POST to /login
3. LoginRequest.php validates (required, string)
4. authenticate() detects input type:
   - filter_var($input, FILTER_VALIDATE_EMAIL) → auth against 'email' column
   - Otherwise → auth against 'username' column
5. On success: regenerate session, redirect to dashboard
6. On failure: throw ValidationException with { username: trans('auth.failed') }
```

## User Model Columns

| Column | Type | Purpose | Unique | Notes |
|--------|------|---------|--------|-------|
| `id` | bigint | Primary key | Yes | |
| `username` | string | Login handle | **Yes** | Alphanumeric + `_.-`, immutable |
| `name` | string | Display name | No | Can be changed in settings |
| `first_name` | string|null | First name (WP-style) | No | Future use |
| `last_name` | string|null | Last name (WP-style) | No | Future use |
| `nickname` | string|null | Nickname (WP-style) | No | Future use |
| `email` | string | Email address | **Yes** | Lowercase |
| `password` | string | Bcrypt hash | — | |
| `remember_token` | string|null | "Remember me" token | — | Sanctum |

## Registration

- New users auto-assigned `customer` role (configurable in Settings)
- `name` auto-populated from `username` on creation
- Email verification via Laravel's `MustVerifyEmail` trait
- Rate limited to 5 attempts per minute

## Auth Pages Component Usage

| Component | Import | Pages Using |
|-----------|--------|-------------|
| `PasswordInput` variant="login" | `@/Components/ui/password-input` | Login, ConfirmPassword |
| `PasswordInput` variant="register" | `@/Components/ui/password-input` | Register, ResetPassword |
| `ErrorMessage` variant="field" | `@/Components/ui/error-message` | Per-field errors |
| `ErrorMessage` variant="alert" | `@/Components/ui/error-message` | Top-level alerts |
| `Spinner` | `@/Components/ui/spinner` | All submit buttons when processing |
| `Alert` (default) | `@/Components/ui/alert` | Success messages |
| `Alert` variant="destructive" | `@/Components/ui/alert` | Error messages |
| `Field`, `FieldLabel`, `FieldError`, `FieldDescription` | `@/Components/ui/field` | Form layout |
| `cn()` | `@/lib/utils` | Class merging |
| `zodErrorsToMap()` | `@/lib/utils` | Zod → field error map |
| `loginSchema`, `registrationSchema`, etc. | `@/lib/schemas` | Zod validation schemas |
| `useAuth()` | `@/hooks/use-auth` | Auth state consumer |

## Client-Side Validation (Zod)

All auth forms have Zod schemas in `resources/js/lib/schemas/index.ts`:

- **`loginSchema`**: `username` (min 1), `password` (min 1)
- **`registrationSchema`**: `username` (3-60, alphanumeric), `email` (valid email), `password` (min 8), `password_confirmation` + superRefine password match
- **`forgotPasswordSchema`**: `email` (valid email)
- **`resetPasswordSchema`**: `password` (min 8), `password_confirmation` + superRefine password match

Validation runs on submit (not on change). On failure, errors are set via `setError()` which Inertia surfaces in the form errors object.

## Server-Side Validation

| Controller | FormRequest/Rules |
|------------|------------------|
| Login | `LoginRequest.php`: `username` (required, string), `password` (required, string) |
| Register | Inline in `RegisteredUserController`: `username` (required, unique, regex:/^[a-zA-Z0-9_.-]+$/), `email` (required, unique, lowercase), `password` (required, confirmed, Password::defaults()-8 chars) |
| Forgot Password | Inline in `ForgottenPasswordController` via `Password::broker()->sendResetLink()` |
| Reset Password | Inline in `NewPasswordController`: `token` (required), `email` (required, email), `password` (required, confirmed, Password::defaults()) |

## Auth Middleware Stack

| Route Group | Middleware | Notes |
|-------------|-----------|-------|
| `/login`, `/register` | `guest` | Redirects to dashboard if already logged in |
| `/forgot-password`, `/reset-password` | `guest` | |
| `/verify-email` | `auth` | |
| `/confirm-password` | `auth` | |
| `/dashboard/*` | `auth`, `verified` | All dashboard pages |
| `/admin/*` | `auth`, `verified`, `admin` | Plus `permission:` checks per-module |
| `/profile/*` | `auth` | Breeze profile pages |

## Flash Messages

- **Success**: `session()->flash('flash', ['success' => '...'])` or `session()->flash('flash', ['info' => '...'])`
- **Status**: `session()->flash('status', '...')` (auth status messages)
- **Error**: `session()->flash('error', '...')`

Bridged in `HandleInertiaRequests::share()` via a single `'flash'` prop that reads from all three session keys. Pages use Base UI `toast.add()` from `@/Components/ui/toast` on mount to display them.

## All 6 Auth Pages Coverage Matrix

| Page | Route | Layout | autoFocus | Spinner | Zod Validation | Field Errors | Alert Errors | Success State | Back Link |
|------|-------|--------|-----------|---------|----------------|--------------|--------------|---------------|-----------|
| Login | `/login` | Guest | username | ✓ | ✓ | ✓ | ✓ (pass reset) | ✓ (password changed) | Register link |
| Register | `/register` | Guest | username | ✓ | ✓ | ✓ | ✓ (register global) | — | Sign in link |
| Forgot Password | `/forgot-password` | Guest | email | ✓ | ✓ | ✓ | ✓ | ✓ (CheckCircle) | Back to login |
| Reset Password | `/reset-password/{token}` | Guest | password | ✓ | ✓ | ✓ | ✓ | ✓ (full success UI + auto-redirect 3s) | Back to login |
| Confirm Password | `/confirm-password` | Guest | password | ✓ | — | ✓ | ✓ | — | — |
| Verify Email | `/verify-email` | Guest | — | ✓ | — | — | ✓ (error) | ✓ (CheckCircle) | Log out |

## Accessibility

- **Labels**: All inputs have `<Label htmlFor="...">` linked to input `id`
- **Error association**: `aria-describedby` on inputs referencing error `id`
- **Invalid state**: `aria-invalid` on inputs when error present
- **Error role**: `<FieldError role="alert">` for screen reader announcements
- **Button state**: `disabled={processing}` with `Spinner` icon
- **Focus management**: `autoFocus` on first field of each form
- **Aria hidden**: Spinner uses `aria-hidden="true"`
- **Keyboard**: All forms submit on Enter; Tab navigates between fields

## Rate Limiting

- Login: 5 attempts per minute via `LoginRequest::ensureIsNotRateLimited()`
- Forgot Password: Laravel's `Password::broker()` handles throttling
- Register: No built-in limiter (SQLite unique constraints prevent duplicates)

## Session & Remember Me

- Session-based auth via Laravel's `SESSION_DRIVER=file`
- "Remember me" uses Sanctum's `remember_token` column
- Session regenerated on login (`regenerate()` + `previousUrl()` preservation)

## Account Deletion

DangerZoneSection (`resources/js/Components/account/sections/DangerZoneSection.tsx`):
- 10s countdown before allowing delete
- Posts to `route("account.destroy")`
- ModuleRegistry::cleanupUserData() iterates all 4 modules calling onUserDelete()
- Shop orders are orphaned (SET NULL), not deleted

## Admin CRUD

| Entity | Controller | FormRequest | Frontend |
|--------|-----------|-------------|----------|
| Users | `Admin/UserController` | `StoreUserRequest`, `UpdateUserRequest` | `Admin/Users/Index`, `Form` |
| Roles | `Admin/RoleController` | Inline validation | `Admin/Roles/Index`, `Form` |

- StoreUserRequest validates: `username` (required, unique, alphanumeric + 3-60 chars), `name`, `email`, `password`, `role_id`
- UpdateUserRequest same but unique rules ignore current user
- Index shows username, display name, email, role badge, status, join date
- Search covers name, username, and email
