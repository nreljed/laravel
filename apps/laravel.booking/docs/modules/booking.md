# Booking Module

Manage guest bookings, availability, and reservations.

- **Slug**: `bookings`
- **Version**: `1.0.0`
- **Dependencies**: `properties`
- **Has Settings**: Yes
- **Nav Label**: Manage Booking

## Permissions

| Slug | Name |
|------|------|
| `bookings.view` | View Bookings |
| `bookings.create` | Create Bookings |
| `bookings.edit` | Edit Bookings |
| `bookings.delete` | Delete Bookings |

## Routes

Three route files:

| File | Prefix | Auth | Middleware |
|------|--------|------|------------|
| `Routes/web.php` | `/dashboard/bookings` | Required | `bookings.view` |
| `Routes/my-bookings.php` | `/dashboard` | Required | `bookings.view` |
| `Routes/guest.php` | `/` | Public | None |

### Auth Routes (`web.php`)

```php
// CRUD
Route::get('/',             [BookingController::class, 'index'])->name('index');
Route::get('/create',       [BookingController::class, 'create'])->name('create');
Route::post('/',            [BookingController::class, 'store'])->name('store');
Route::get('/{booking}/edit', [BookingController::class, 'edit'])->name('edit');
Route::put('/{booking}',    [BookingController::class, 'update'])->name('update');
Route::get('/{booking}',    [BookingController::class, 'show'])->name('show');
Route::delete('/{booking}', [BookingController::class, 'destroy'])->name('destroy');
Route::post('/{booking}/transition', [BookingController::class, 'transition'])->name('transition');

// Calendar & Availability
Route::get('/calendar/data', ...);
Route::get('/availability', ...);
// ... availability block/unblock/check routes

// iCal import/feeds
Route::post('/ical/import', ...);

// Global Settings (admin)
Route::get('/global-settings', ...);
Route::put('/global-settings', ...);

// Demo tooling, guest portal, CSV export, bulk, archive, actions...
```

Booking configuration (pricing, rules, availability, units, area) is managed via the **BookingSection** inside the Property Form (`Properties/Resources/js/components/BookingSection.tsx`). See `docs/modules/properties.md`. Manual bookings can be created via the `/dashboard/bookings/create` form (`Resources/js/Pages/Form.tsx`).

### My Bookings (`my-bookings.php`)

Guest-facing: index, show, cancel, modify.

### Public Routes (`guest.php`)

Booking view by token, magic link login, inline registration, cancellation/modification requests.

## Nav Items

```
Manage Booking [host]   (SidebarGroupLabel)
  └─ Bookings [host]     (collapsible parent)
      ├─ All Bookings
      ├─ New Booking
      └─ Calendar

My Trips                 (SidebarGroupLabel, guest mode)
  └─ My Bookings [guest] (flat link)
```

The `bookings.view` permission gates the entire nav group. When `userMode === "guest"` the nav switches to "My Trips" / "My Bookings". The "New Booking" item links to the create form at `/dashboard/bookings/create`.

## Dashboard Stats

- `total` — All bookings count
- `pending` — Pending status count
- `confirmed` — Confirmed status count

## Host/Guest Mode

This module owns the host/guest mode toggle:

- `canSwitchMode` requires `bookings.view` permission AND the module must be enabled in the DB
- Host mode shows full Booking Management nav
- Guest mode shows "My Trips" with only "My Bookings"
- Admin follows the same mode rules (no bypass)
- Mode stored in session, toggle via `POST /dashboard/user/mode`

## Email Templates

10 templates in the `Booking` group:

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

## Notification Types

| Type | Channels | Template |
|------|----------|----------|
| `booking.created` | in_app + email | `booking-new-notification` |
| `booking.cancelled` | in_app + email | `booking-cancellation` |
| `booking.confirmed` | in_app + email | `booking-confirmation` |
| `booking.modified` | in_app + email | (dynamic template) |

## Event Listeners

Booted on `booking.created`, `booking.status-transition`, `booking.deleted`, `booking.saved`:

- **Created**: Sends confirmation email + magic link + admin notification
- **Status Transition**: Sends status-based email to guest
- **Deleted**: Busts property cache, revokes guest tokens
- **Saved**: Busts property cache

## Repeater Configs

- `house_rules` — max 20, 5 field types (title, icon, description, status, order)
- `seasonal_rates` — max 50, 4 field types (name, start/end date, daily rate, status)

## Gallery Config

- `damage_photos` — max 20 images, upload path `bookings/damage`, hidden from media library

## Migration Paths

Returns `[]` — no module-specific migrations. All booking tables are created via application-level migrations.

## User Cleanup

On user deletion, all bookings owned by that user are deleted.

## Services

| Service | Purpose |
|---------|---------|
| `BookingCacheService` | Cache busting for property-level booking data |
| `BookingEmailService` | Guest-facing email sending |
| `BookingGuestTokenService` | Magic link token generation/revocation |
| `BookingPipelineTrace` | Debug trace logging for booking lifecycle |
| `BookingRefundMailService` | Refund notification emails |

## Architecture

### Booking Config is Property Data

All per-property booking settings (pricing, rules, availability, units, area info) are stored in the `properties.metadata` JSON column under `booking_enabled` and individual setting keys. The `BookingPropertyIntegrationService` (`app/Booking/Services/BookingPropertyIntegrationService.php`) acts as the bridge:

- `isModuleActive()` — checks `FeatureRegistry::get('bookings')?->isEnabled()`
- `savePropertyBookingData()` — writes to `properties.metadata`
- `getPropertyBookingData()` — reads from `properties.metadata`

### Single Workflow

```
Properties → Edit Property → BookingSection (5 tabs) → Save
                                                               
If the user needs to edit property images or meta:             
the BookingSection is already inside the Property Form —      
one place for everything.                                      
```

No separate booking settings page. Booking records can be created manually via the create form (`/dashboard/bookings/create`, `Form.tsx`), or by guests via the public widget. Existing bookings can be edited via the edit form (`/dashboard/bookings/{booking}/edit`, also `Form.tsx`).
