# AccountService

`app/Services/AccountService.php`

Handles user account operations: email changes, password changes, account deletion with full data cleanup.

Dependencies: `MailService`

## Methods

### `updateEmail(User $user, string $email)`

Updates user email. If `require_email_verification` setting is enabled, resets `email_verified_at` to null and sends verification. Sends email change notification to the old address. Logs an audit entry.

### `updatePassword(User $user, string $currentPassword, string $newPassword)`

Validates current password, ensures new password differs from current, hashes and updates. Sends password change confirmation email. Logs audit.

### `verifyPassword(User $user, string $password)`

Checks password against hash. Returns `['success' => true, 'verified' => true]` or `['success' => false, 'verified' => false]`.

### `deleteAccount(User $user)`

Logs audit, calls `deleteAllUserData()`, deletes the user, sends deletion confirmation email.

### `deleteAllUserData(User $user)`

1. Calls `ModuleRegistry::cleanupUserData($userId)` — iterates all modules calling `onUserDelete()` (cleans bookings, posts, orphaned orders, etc.)
2. Deletes all API tokens
3. Deletes all notifications (sent + received)
4. Deletes notification preferences

## Email Categories

| Method | Category |
|--------|----------|
| `sendPasswordChangeConfirmation()` | `password-changed` |
| `sendEmailChangeNotification()` | `email-changed` |
| `sendDeletionConfirmationEmail()` | `account-deleted` |

## Usage

- Called by `ProfileController` for account editing (My Account page).
- Called by `AccountController` for account management.
