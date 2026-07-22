# HandleInertiaRequests Middleware

`app/Http/Middleware/HandleInertiaRequests.php`

Extends `Inertia\Middleware`. All Inertia shared props are defined here as lazy closures.

## Shared Props

| Prop                      | Type         | Description                                              |
| ------------------------- | ------------ | -------------------------------------------------------- |
| `flash`                   | object       | Session flash messages (`status`, `error`, `mail_debug`) |
| `csp_nonce`               | string       | CSP nonce from request attribute                         |
| `auth.user`               | object\|null | Authenticated user (see `buildAuthUser()` below)         |
| `latestNotifications`     | array        | Latest 5 notifications                                   |
| `userCapabilities`        | object       | Permission flags                                         |
| `userMode`                | string       | `"host"` or `"guest"` (from session, default `guest`)    |
| `canSwitchMode`           | bool         | Always `false` (simplified)                              |
| `formattingSettings`      | object       | Currency, surface, date/time format defaults             |
| `featureNavItems`         | array        | Nav item definitions from all booted features            |
| `featureData`             | object       | Shared data from all booted features                     |
| `emailSettings`           | object       | SMTP settings for frontend                               |
| `emailTemplates`          | object       | Email template definitions                               |
| `brand`                   | object       | `{ logo: string, name: string }` from settings           |
| `app_url`                 | string       | App URL from settings                                    |
| `notificationUnreadCount` | number       | Unread notification count                                |
| `debug`                   | bool         | `APP_DEBUG` value                                        |

## `buildAuthUser()` Return Shape

```json
{
  "id": 1,
  "name": "admin",
  "email": "admin@example.com",
  "user_email": "admin@example.com",
  "avatar": "/storage/avatars/...",
  "custom_avatar_url": "/storage/avatars/...",
  "display_name": "Admin",
  "display_name_options": {},
  "user_login": "admin",
  "first_name": "",
  "last_name": "",
  "nickname": "",
  "url": "",
  "website": "",
  "description": "",
  "user_registered": "2026-01-01T00:00:00.000000Z",
  "roles": ["admin"],
  "role": "admin",
  "profile_data": {
    "properties": {
      "phone_numbers": [],
      "social_media": []
    }
  }
}
```

## `userCapabilities` Structure

```json
{
  "isAdmin": true,
  "can": {
    "manageUsers": true,
    "manageSettings": true,
    "manageRoles": true,
    "viewSystemHealth": true,
    "viewDashboard": true,
    "viewProfile": true,
    "editProfile": true,
    "deleteAccount": true,
    "viewNotifications": true,
    "manageNotifications": true,
    "postsView": true,
    "postsCreate": true,
    "postsEdit": true,
    "postsDelete": true
  }
}
```
