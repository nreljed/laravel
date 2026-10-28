# Notifications

The notification system covers in-app (database), email, and push (Web Push) channels with
per-user preferences.

## Models

| Model                    | Table                      | Notes                                                                                                                                                                     |
| ------------------------ | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Notification`           | `notifications`            | Polymorphic; structured `data` with `title`/`message`/`link`/`severity`/`module` + `type` key. `creating` hook fills fields from `data` and invalidates the unread cache. |
| `NotificationPreference` | `notification_preferences` | Per-user per-type channel + enabled toggles.                                                                                                                              |
| `PushSubscription`       | `push_subscriptions`       | Web Push endpoint/keys per user.                                                                                                                                          |

## Services

- **`NotificationService`** — central dispatch:
  - `send(User $user, string $type, array $data)` — builds an `AppNotification`, honors the
    user's per-type preference (`notifyViaPreferences`), writes to the database channel and
    sends email/push when enabled for the type.
  - `invalidateUnreadCache($userId)` — clears the cached unread count.
  - Unread-count + recent queries power the notification bell (20s polling via
    `Api\NotificationPollController`).
- **`PushNotificationService`** — Web Push via `PushSubscription`s (VAPID).
- **`NotificationTypeRegistry`** — aggregates `core.notification_types` +
  every feature provider's `getNotificationTypes()`.

## Notification Types

Each type: `label`, `description`, `group`, `severity`, `default_channels`
(`in_app` / `mail` / `push`), `default_enabled`, optional `email_template`.

Core ships:

| Type                   | Group   | Severity | Channels |
| ---------------------- | ------- | -------- | -------- |
| `database.maintenance` | System  | critical | in_app   |
| `user.welcome`         | Account | info     | mail     |

Features register their own (e.g. Shop: `order.placed`, `order.status_changed`, `low_stock`,
`product.stock_changed`). The account "Notifications" page lets users opt in/out per type.

## Notifications (PHP classes)

| Class                             | Purpose                                                                                 |
| --------------------------------- | --------------------------------------------------------------------------------------- |
| `AppNotification`                 | Generic structured in-app notification (database channel).                              |
| `VerifyEmail` / `ResetPassword`   | Core replacements for Laravel's auth mailables (swapped in `configureNotifications()`). |
| `WelcomeNewUser`                  | Sent on account creation.                                                               |
| `DatabaseMaintenanceNotification` | Alert when an admin runs reset/reseed via the admin UI or deploy endpoint.              |

## Emails

- **`MailService`** — generic mailer (`send()` with a template + data, works for guest
  recipients with no `User` notifiable). Uses `MailerConfigurator` for DB-driven transport.
- **`MailerConfigurator`** — reads mail settings from the `settings` table (host, port,
  encryption, credentials) at boot.
- **`EmailTemplateService`** + **`EmailSettingsController`** — template registry (sample
  data per feature via `getEmailTemplates()`), template simulator, and settings UI.
- Templates: `resources/views/email/templates/*.blade.php` (`welcome`, `verify-email`,
  `password-reset`, `password-changed`, `email-changed`, `new-user`, `new-user-admin`,
  `account-deleted`, `test`, `default`) using the shared `email/layouts/email.blade.php`
  layout. All templates use null coalescing (`??`) for safe fallbacks; `$siteName`,
  `$userName`, `$notificationLink` are merged globally by `AppNotification::toArray()`.

## Flow

```
Event (e.g. order.placed)
   → NotificationService::notifyViaPreferences(user, type, data)
       ├─ type enabled + channel allowed?
       │    ├─ in_app → AppNotification (database)
       │    ├─ mail   → template email (MailService)
       │    └─ push   → PushNotificationService (VAPID)
       └─ invalidateUnreadCache(userId)
Notification bell polls /api/notifications/unread-count + /api/notifications/recent
```

## Related

- [Settings](settings.md) — notification prefs live in the account page.
- [Blog](https://github.com/nreljed/blog) / Shop docs — how features register types via `getNotificationTypes()`.
