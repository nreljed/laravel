# Shared Props (HandleInertiaRequests)

All data shared to Inertia via `HandleInertiaRequests::share()`:

## Auth

| Prop | Type | Source |
|------|------|--------|
| `auth.user` | `User \| null` | Current user |
| `auth.user.isAdmin` | `boolean` | Computed admin check |
| `impersonating` | `boolean` | Active impersonation |

## Notifications

| Prop | Type | Description |
|------|------|-------------|
| `latestNotifications` | `array` | Recent notifications |
| `notificationUnreadCount` | `integer` | Unread count for badge |

## Flash Messages

| Prop | Type | Description |
|------|------|-------------|
| `flash.success` | `string \| null` | Success message |
| `flash.error` | `string \| {title, message} \| null` | Error message |
| `flash.warning` | `string \| null` | Warning message |
| `flash.info` | `string \| null` | Info message |

## Mode & Permissions

| Prop | Type | Description |
|------|------|-------------|
| `userMode` | `'host' \| 'guest'` | Current display mode |
| `canSwitchMode` | `boolean` | Whether toggle is available |
| `userCapabilities` | `Record<string, boolean>` | Flat permission map |

### userCapabilities Structure

```typescript
{
    isAdmin: boolean,
    can: {
        manageUsers: boolean,
        manageSettings: boolean,
        manageRoles: boolean,
        managePermissions: boolean,
        viewAuditLogs: boolean,
        impersonate: boolean,
        manageSessions: boolean,
    },
    'bookings.view': boolean,
    'bookings.create': boolean,
    // ... all module permissions
    hasModule_bookings: boolean,
    hasModule_properties: boolean,
    // ... all module enablement flags
}
```

## Module Data

| Prop | Type | Description |
|------|------|-------------|
| `moduleNavItems` | `NavItem[]` | Sidebar items with `groupLabel` |
| `modules` | `array` | Module DB records |
| `moduleDebugData` | `array \| null` | Debug info (admin only) |

## Config

| Prop | Type | Description |
|------|------|-------------|
| `repeaterConfigs` | `object` | All module repeater field configs |
| `profileFieldConfigs` | `object` | Profile extension field configs |
| `emailSettings` | `object` | Email/SMTP configuration |
| `brand` | `object` | Brand name, logo |
| `booking_global_settings` | `object` | Booking module settings |
