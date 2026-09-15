# NotificationService

`app/Services/NotificationService.php`

Central dispatch for sending notifications respecting user preferences.

Dependencies: `NotificationTypeRegistry`, `ModuleRegistry`, `MailService`

## Methods

### `notifyViaPreferences($notifiable, string $type, Notification $notification, ?array $channels = null)`

Central dispatch method. For each channel (in_app, email):

1. Looks up user's `NotificationPreference` for the given type + channel
2. If preference exists and is disabled, skips that channel
3. If no preference exists, falls back to `default_enabled` flag on the `NotificationType` definition
4. Sends in-app notification (via `$notifiable->notify()`)
5. If email is enabled, calls `sendNotificationEmail()` to build and dispatch via `MailService`

### `sendNotificationEmail($notifiable, Notification $notification, string $templateKey)`

Builds and sends email using the module-defined email template. Merges notification data with user info.

### `purge(int $retentionDays = 90)`

Hard-deletes all notifications older than the retention period. Returns count of deleted records. Useful for a scheduled maintenance task.

## Notification Preference System

Users can opt in/out of specific notification channels per type via `NotificationPreference` model. The system:

- Reads `NotificationType` definitions from all modules (via `NotificationTypeRegistry`)
- Stores preferences in `notification_preferences` table
- Default behavior is defined by `default_enabled` on each type definition
