# Booking Module — Architecture

## Data Model

### Booking Table

The `bookings` table is the central entity, created via application-level migrations:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint AI | Primary key |
| `user_id` | bigint FK→users | Linked account (nullable for guest bookings) |
| `property_id` | bigint FK→properties | Property being booked |
| `status` | string | Current status (enum value) |
| `guest_name` | string | Guest's full name |
| `guest_email` | string | Guest's email address |
| `guest_phone` | string nullable | Guest's phone number |
| `start_date` | date | Check-in date |
| `end_date` | date | Check-out date |
| `check_in_time` | time | Check-in time |
| `check_out_time` | time | Check-out time |
| `timezone` | string | Booking timezone |
| `guests` | integer | Number of guests |
| `has_pets` | boolean | Whether pets are included |
| `special_requests` | text nullable | Guest special requests |
| `total_price` | decimal | Total booking price |
| `nightly_rate` | decimal | Per-night rate |
| `cleaning_fee` | decimal | Cleaning fee |
| `service_fee` | decimal | Service fee |
| `deposit_amount` | decimal nullable | Security deposit |
| `deposit_status` | string nullable | `collected`, `refunded`, `claimed` |
| `cancellation_policy` | string | Policy applied to this booking |
| `metadata` | json nullable | Flexible metadata (unit, refund, etc.) |
| `checked_in_at` | datetime nullable | When guest checked in |
| `checked_out_at` | datetime nullable | When guest checked out |
| `cancelled_at` | datetime nullable | When cancelled |
| `created_at` | timestamp | Created |
| `updated_at` | timestamp | Updated |
| `deleted_at` | timestamp soft-delete | Soft delete |

### BookingAction Table

Audit log for booking operations:

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint AI | Primary key |
| `booking_id` | bigint FK→bookings | Related booking |
| `action` | string | Action type (check_in, check_out, cancel, modify) |
| `performed_by` | string nullable | Who performed the action |
| `metadata` | json nullable | Additional context |
| `created_at` | timestamp | Created |

### BookingStatus Enum (`app/Modules/Booking/Enums/BookingStatus.php`)

PHP 8.1 backed string enum:

```php
enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Completed = 'completed';
}
```

### Allowed Transitions (`canTransitionTo`)

| From | To |
|------|----|
| `Pending` | `Confirmed`, `Cancelled` |
| `Confirmed` | `CheckedIn`, `Cancelled`, `NoShow` |
| `CheckedIn` | `CheckedOut`, `Cancelled` |
| `CheckedOut` | `Completed`, `Cancelled` |
| `NoShow` | `Cancelled` |
| `Cancelled` | _(none — terminal)_ |
| `Completed` | _(none — terminal)_ |

### BookingStatusManager

Handles all status transitions via `BookingStatusManager::transition()`. Sets timestamps (`checked_in_at`, `checked_out_at`, `cancelled_at`) and fires `booking.status-transition` event.

### BookingMeta (`app/Modules/Booking/Support/BookingMeta.php`)

Constants class with 65+ field definitions for booking metadata keys and validation rules.

## Booking Config Architecture

```
Properties → Edit Property → BookingSection (5 tabs) → Save into properties.metadata
```

Booking settings are NOT stored in a separate table. They live in the `properties.metadata` JSON column:

- `booking_enabled` — Whether booking is enabled for this property
- `nightly_rate`, `cleaning_fee`, `service_fee` — Pricing
- `seasonal_rates_json` — Seasonal rate adjustments
- `length_of_stay_rates_json` — Per-length pricing
- `length_of_stay_tiers_json` — Discount tiers
- `unit_config_json` — Bookable units configuration
- `cancellation_policy` — Default policy
- `check_in_time`, `check_out_time` — Default times
- Various toggle flags

## Frontend Data Flow

```
Page Load:
  HandleInertiaRequests
    → shares moduleNavItems, userCapabilities, booking_global_settings
    → guest routes share booking data + guest token

Booking CRUD (Admin):
  BookingController → Inertia::render('Bookings/Form') → Form.tsx
  Form.tsx → POST/GET → BookingController@store/update

Guest Portal (Token-based):
  BookingGuestController → Inertia::render('Bookings/GuestView') → GuestView.tsx
  GuestView.tsx → fetch() → guestCheckIn/guestCheckOut/requestCancellation

My Bookings (Authenticated):
  BookingGuestController → Inertia::render('Bookings/MyBookings') → MyBookings.tsx
  MyBookings.tsx → router.post() → loggedInCheckIn/loggedInCheckOut
```
