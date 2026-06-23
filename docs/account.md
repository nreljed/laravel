# Account Page

**Route:** `/dashboard/account` (named `dashboard.account`)  
**Controller:** `AccountController@edit`  
**Layout:** `AuthenticatedLayout` with `header="Account"`  

---

## Sections

The account page is a single-page composition of four sections, listed top to bottom.

### 1. Email Verification Banner

*Conditional* — only shown when `mustVerifyEmail` is `true` (both the `User` model implements `MustVerifyEmail` AND `require_email_verification` setting is enabled) AND `email_verified_at` is `null`.

- Amber card with `AlertTriangle` icon
- Message: "Email not verified — Please verify your email address to access all features."
- **Resend** button → calls `POST /email/verification-notification` (`route('verification.send')`)
- `mustVerifyEmail` is `false` when `require_email_verification` is `'0'` — banner never shows
- No aggressive redirect — user can browse freely, verification is a soft prompt
- After clicking link in email → lands on dedicated **Email Verified** page (`/email/verified`, route `verification.verified`) with success checkmark and "Continue to Dashboard" button

### 2. Email Address

**Component:** `EmailSection`  
**RPC:** `POST /dashboard/account/email` (named `account.update-email`)

- **Current Email** — disabled input with `bg-muted cursor-not-allowed`
- **Badge** — "Verified" (`bg-green-50 text-green-700`) or "Unverified" (`bg-amber-50 text-amber-700`) based on `email_verified_at`
- **New Email Address** — text input with client-side format regex validation
- **Current Password** — required to confirm identity (security gate — ownership proof); per-field inline error display
- **Update Email** button — disabled until current password is filled
- On success: `AccountService::updateEmail()`:
  - Uses individual attribute assignment (`$user->email = ...; $user->email_verified_at = null; $user->save()`) — **NOT** mass-assignment `update()`, because `email_verified_at` is not in `$fillable`
  - Nullifies `email_verified_at` only if `require_email_verification` is `'1'`
  - Sends verification notification via Laravel's native `sendEmailVerificationNotification()` (synchronous)
  - Notifies old email via `sendEmailChangeNotification()` using `MailService::sendNow()` (synchronous, not queued)
- Verification email sent only when setting is enabled; success message adapts ("verification link sent" vs plain "Email updated successfully")

### 3. Change Password

**Component:** `PasswordSection`  
**RPC:** `POST /dashboard/account/password` (named `account.update-password`)

- Current password + new password (with strength bar + confirmation)
- Min 8 characters, password confirmation required
- On success: sends "password changed" confirmation email via `MailService`
- Validates: current password must match (Laravel's `current_password` rule), new password must differ from current (`Hash::check`)
- **Identity gate**: current password alone is the security proof — no extra email verification required (industry standard: GitHub, Google, Slack)
- **Error isolation**: `current_password` errors map to the "Current Password" field, `new_password` errors map to the "New Password" field (no cross-field bleed, fixed field mapping)

### 4. Notification Preferences

**Component:** `NotificationPrefsSection`  
**RPC:** `POST /dashboard/account/notification-preferences` (named `account.notification-preferences`)

- Per-type toggles for **In-app** and **Email** channels — rendered as `<Checkbox>` components (not Switches), matching beta layout
- Layout per row: label+description on left, checkbox+icon+channel label on right, linked via `id`+`htmlFor`
- `CHANNEL_ICONS` map renders inline Lucide icon next to each channel checkbox
- "Muted" badge shown inline next to title when both channels are off for a type
- **Enable All / Disable All** quick buttons
- **Notification Sound** toggle — switch persisted in `localStorage` key `notification_sound_enabled`
- **Save Preferences** button — disabled until changes detected
- Payload: `{ preferences: [{ type, channel, enabled }] }`

### 5. Delete Account (Danger Zone)

**Component:** `DangerZoneSection`  
**RPC #1 (pre-check):** `POST /dashboard/account/verify-delete-password` (named `account.verify-delete-password`)  
**RPC #2 (execute):** `POST /dashboard/account/delete` (named `account.destroy`)

- Accordion with destructive variant styling, "Delete Account" trigger button
- Click opens an `AlertDialog` with:
  - Confirmation text input: type **"delete my account"**
  - **Current Password** input (via `CurrentPasswordInput`) — security gate
  - Generic message: *"All your personal data, content, and account information will be permanently deleted."*
  - **Cancel** button — **always active**, even during countdown
- **Two-phase flow:**
  1. **Pre-check** — `fetch` POST to `verify-delete-password` with `current_password`. Server validates password immediately. On failure → red inline error on password field instantly.
  2. **Countdown** — Only starts after password pre-check passes. 10-second cooldown for user to change mind.
  3. **Execute** — After countdown, `router.post` to `destroy` with `confirmation` + `current_password`.
- **Error handling**: Server validation errors shown inline on respective fields. Dialog stays open. Errors clear on typing.
- **Cancel**: Resets confirmation text, password, countdown, errors, and clears interval.
- Deletes: all personal data, content, account info, tokens, notifications, notification preferences, and module data via `ModuleRegistry::cleanupUserData()`.
- Sends "account deleted" confirmation email synchronously via `MailService::sendNow()`.

---

## Backend Props (from `AccountController@edit`)

| Prop | Type | Description |
|------|------|-------------|
| `user` | `{ id, name, display_name, email, email_verified_at, avatar, user_login }` | Current user data |
| `mustVerifyEmail` | `boolean` | `true` only when `MustVerifyEmail` contract AND `require_email_verification === '1'` |
| `status` | `string?` | Flash status from session (e.g. `"verification-link-sent"`) |
| `notificationTypes` | `Record<string, Record<string, NotificationTypeConfig>>` | Grouped notification type definitions from `NotificationTypeRegistry` |
| `preferences` | `Record<string, { in_app?, email? }>` | Current user notification preferences |

---

## Email Delivery Notes

- Verification emails use Laravel's native notification system (`$user->sendEmailVerificationNotification()`) — sent synchronously, no queue dependency
- Email change notification to old email uses `MailService::sendNow()` — synchronous, not queued
- Password change confirmation uses `MailService::send()` — queued via `SendEmail` job; requires `QUEUE_CONNECTION=sync` (local) or a running worker (`php artisan queue:work`) in production
- With `MAIL_MAILER=log`, all emails are written to `storage/logs/laravel.log` with full HTML; no SMTP setup needed for local dev

## Email Verification (Non-Blocking Model)

The system uses a **soft-prompt** model — inspired by GitHub, Slack, and modern SaaS apps.

| Aspect | Behavior |
|--------|----------|
| `require_email_verification` setting | When `'0'`: `User::hasVerifiedEmail()` always returns `true`. Banner hidden. No gate. |
| After registration | Verification email sent; user lands on dashboard (no redirect) |
| `verified` middleware | **Not applied** to any route group. Removed from `routes/web.php`. |
| Account page banner | Shown only when setting is `'1'` AND user hasn't verified |
| Resend action | `POST /email/verification-notification` (throttled 6/1 min) |
| Email change | Nullifies `email_verified_at`; sends verification only if setting is `'1'` |
| Future sensitive actions | Will check `$request->user()->hasVerifiedEmail()` individually |

The dedicated `/verify-email` page remains accessible for direct visits, but is never auto-redirected to.

---

## Related Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/AccountController.php` | Server-side controller |
| `app/Services/AccountService.php` | Business logic (email, password, delete) |
| `app/Notifications/VerifyEmail.php` | Branded verification email notification |
| `resources/js/Pages/Account/Edit.tsx` | Account page composition |
| `resources/js/Components/account/sections/DangerZoneSection.tsx` | Delete account dialog with two-phase flow |
| `resources/js/Pages/Auth/EmailVerified.tsx` | Verification success page |
| `routes/web.php` | Route definitions (no `verified` middleware) |
| `routes/auth.php` | Email verification routes (`verification.send`, `verification.notice`, `verification.verify`, `verification.verified`) |
