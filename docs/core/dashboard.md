# Dashboard

## Pages

| Route                           | Component                           | Purpose                                   |
| ------------------------------- | ----------------------------------- | ----------------------------------------- |
| `/dashboard`                    | `dashboard/index.tsx`               | Greeting ("Welcome back, {name}!")        |
| `/dashboard/stats`              | `dashboard/stats.tsx`               | KPI cards, charts, analytics              |
| `/dashboard/account`            | `dashboard/account/index.tsx`       | Consolidated profile + account management |
| `/dashboard/notifications`      | `dashboard/notifications/index.tsx` | Notification management                   |
| `/dashboard/notifications/test` | `dashboard/notifications/test.tsx`  | Test email templates                      |

## Services

### DashboardService

`app/Services/DashboardService.php` — cached analytics (5-min TTL):

```php
DashboardService::getAnalytics(); // Returns stats, charts, recent activity
```

### SettingsService

`app/Services/SettingsService.php` — DB-driven app config:

```php
SettingsService::get('app_name', 'Cost Breaker 360');
SettingsService::getFormattingDefaults();
```

## Shared Props

`HandleInertiaRequests` shares these with every page:

| Prop                      | Type   | Source                         |
| ------------------------- | ------ | ------------------------------ |
| `auth.user`               | Object | Current user profile           |
| `latestNotifications`     | Array  | Last 5 notifications           |
| `notificationUnreadCount` | Number | Unread count                   |
| `userCapabilities`        | Object | Admin flag + permission checks |
| `featureNavItems`         | Array  | Dynamic sidebar items          |
| `brand`                   | Object | Logo, app name                 |
| `flash`                   | Object | Status/error messages          |
| `formattingSettings`      | Object | Price/surface formatting       |

## Components

```
Components/
  layout/            # app-sidebar, ImpersonationBanner
  navigation/        # nav-logo, nav-main, nav-user, command-search
  notifications/     # NotificationBell, NotificationItem, NotificationDetailDialog
  shared/            # section, settings-accordion, error-message
  dashboard/         # KpiCard, Sparkline, RevenueChart (if used)
```

## Sidebar

Dynamic sidebar built from `featureNavItems`:

1. `FeatureRegistry::getAllNavItems()` collects nav items from all features
2. `app-sidebar.tsx` renders grouped nav sections
3. Items include: label, icon, url, permission gate
