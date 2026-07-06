# Booking Module — Global Settings

Managed via `BookingGlobalSettingsController`, stored in the application-level `settings` table under the `booking` group.

## Settings Reference

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `allow_guest_cancellation_statuses` | array | `['pending', 'confirmed']` | Statuses where guest can request cancellation |
| `allow_guest_modification_statuses` | array | `['pending', 'confirmed', 'checked_in']` | Statuses where guest can request modification |
| `email_enable_modification_request` | bool | `true` | Send modification request emails |
| `default_cancellation_policy` | string | `'flexible'` | Default policy for new bookings |
| `check_in_time_default` | string | `'15:00'` | Default check-in time |
| `check_out_time_default` | string | `'11:00'` | Default check-out time |
| `timezone_default` | string | `'UTC'` | Default timezone |

## Accessing Settings

```php
use App\Services\SettingsService;

$settings = SettingsService::getGroup('booking');
// Returns ['allow_guest_cancellation_statuses' => [...], ...]
```

## Settings UI

Admin page at `GET /dashboard/bookings/global-settings`, form posts to `PUT /dashboard/bookings/global-settings`.

## Frontend Consumption

Settings are shared to Inertia via `HandleInertiaRequests` as `booking_global_settings` prop, consumed by `GuestView.tsx`, `MyBookings.tsx`, and `MyBookingDetail.tsx`.

```typescript
const globalProps = usePage().props as Record<string, unknown>;
const allowedCancelStatuses = (
  globalProps.booking_global_settings as Record<string, unknown>
)?.allow_guest_cancellation_statuses as string[];
```
