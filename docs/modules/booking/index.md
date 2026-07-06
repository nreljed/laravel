# Booking Module

Manage guest bookings, availability, and reservations.

- **Slug**: `bookings`
- **Version**: `1.0.0`
- **Dependencies**: `properties`
- **Has Settings**: Yes
- **Nav Group Label**: Manage Booking

## Sub-Documents

| Document | Description |
|----------|-------------|
| [Architecture](architecture.md) | Data model, status workflow, booking config |
| [Controllers](controllers.md) | All controller methods reference |
| [Services](services.md) | All 6+ services reference |
| [Frontend](frontend.md) | Inertia pages, components |
| [Settings](settings.md) | Global settings reference |
| [Guest Portal](guest-portal.md) | Check-in/check-out, magic-link, guest views |

## Quick Reference

| Attribute | Value |
|-----------|-------|
| **Permissions** | `bookings.view`, `create`, `edit`, `delete` |
| **Route Files** | `web.php` (auth), `my-bookings.php` (auth), `guest.php` (public), `api.php` (public API) |
| **Dashboard Stats** | `total`, `pending`, `confirmed` |
| **Repeater Configs** | `house_rules`, `seasonal_rates`, `length_of_stay_rates`, `length_of_stay_tiers`, `unit_config` |
| **Gallery Config** | `damage_photos` (max 20, hidden from library) |
| **Email Templates** | 11 templates in `Booking` group |
| **Notification Types** | `booking.created`, `cancelled`, `confirmed`, `modified` |
| **Migration Paths** | `[]` — no module-specific migrations |

### Status Workflow

```
Pending ──→ Confirmed ──→ CheckedIn ──→ CheckedOut ──→ Completed
  │            │             │              │
  └── Cancelled ←───────────┴──────────────┘
                              NoShow ──→ Cancelled
```

### Route Files

| File | Prefix | Middleware | Purpose |
|------|--------|------------|---------|
| `Routes/web.php` | `/dashboard/bookings` | `auth`, `verified`, `bookings.view` | CRUD, Calendar, Demo, Settings |
| `Routes/my-bookings.php` | `/dashboard` | `auth`, `verified`, `bookings.view` | Guest-facing my bookings |
| `Routes/guest.php` | `/booking/...` | `web` (public) | Token-based guest portal |
| `Routes/api.php` | `api/booking/...` | `web` (public) | Public API endpoints |

### Nav Items

```
Manage Booking [host]        (SidebarGroupLabel)
  └─ Bookings [host]         (collapsible parent, "Host" badge)
      ├─ All Bookings
      ├─ New Booking
      └─ Calendar

My Trips                     (SidebarGroupLabel, guest mode)
  └─ My Bookings [guest]     (flat link, no collapsible)
```

The `bookings.view` permission gates the entire nav group. When `userMode === "guest"` the nav switches to "My Trips" / "My Bookings".

### Event Listeners

| Event | Handler | Action |
|-------|---------|--------|
| `booking.created` | `onBookingCreated` | Send confirmation email + magic link + admin notification |
| `booking.status-transition` | `onBookingStatusTransition` | Send status-based email + cancellation/refund handling |
| `booking.deleted` | `onBookingDeleted` | Bust property cache, revoke guest tokens |
| `booking.saved` | `onBookingSaved` | Bust property cache |

### Booking Config is Property Data

All per-property booking settings are stored in `properties.metadata` JSON column. `BookingPropertyIntegrationService` bridges the two modules:

- `isModuleActive()` — checks `ModuleRegistry::isEnabled('bookings')`
- `savePropertyBookingData()` — writes to `properties.metadata`
- `getPropertyBookingData()` — reads from `properties.metadata`

### Email Templates

| Key | Description |
|-----|-------------|
| `booking-confirmation` | Booking Confirmation |
| `booking-cancellation` | Booking Cancellation |
| `booking-status-change` | Booking Status Change |
| `booking-new-notification` | New Booking Notification (Admin) |
| `booking-pre-arrival` | Pre-Arrival Instructions |
| `booking-post-stay` | Post-Stay Follow-up |
| `booking-cancel-request` | Cancel Request (Guest Confirmation) |
| `booking-checkin-instructions` | Check-In Instructions |
| `booking-guest-received` | Booking Received (Guest) |
| `booking-checkout-reminder` | Check-Out Reminder |
| `booking-refund` | Refund Summary |

### User Cleanup

On user deletion, all bookings owned by that user are deleted.
