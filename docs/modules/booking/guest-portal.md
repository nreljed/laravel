# Booking Module — Guest Portal

## Overview

The guest portal allows guests to manage their bookings without creating an account. Authentication is via **magic link tokens** (sent by email) or optional **inline login** if they already have an account.

## Access Methods

### 1. Token-Based (No Account)

Guest receives a magic link via email when booking is created:

```
GET /booking/show/{bookingId}?token={rawToken}
```

The `BookingGuestTokenService` validates the token. The guest can:

- View booking details
- Request cancellation
- Request modification (dates/guests)
- Check in / Check out
- Create an account from their booking

### 2. Authenticated (Has Account)

Guests who create an account can access:

```
GET /dashboard/my-bookings              — List of bookings
GET /dashboard/my-bookings/{bookingId}   — Booking detail
```

With session authentication, they can perform all actions without tokens.

## Check-In / Check-Out

### Frontend Buttons

| Status | Button | Label | Route | Auth Type |
|--------|--------|-------|-------|-----------|
| `Confirmed` | Check In | "I've Arrived" | `booking.check-in` | Token |
| `Confirmed` | Check In | "I've Arrived" | `dashboard.my-bookings.check-in` | Session |
| `CheckedIn` | Check Out | "I'm Leaving" | `booking.check-out` | Token |
| `CheckedIn` | Check Out | "I'm Leaving" | `dashboard.my-bookings.check-out` | Session |

### Backend Flow

```
Button Click → POST /booking/check-in/{bookingId}
  → BookingGuestController::guestCheckIn()
  → acquireLock(bookingId)          [prevents concurrent requests]
  → validateToken(bookingId, token)  [403 if invalid]
  → find booking                     [404 if not found]
  → canTransitionTo(CheckedIn)       [400 if not allowed]
  → BookingStatusManager::transition()  [sets checked_in_at, fires event]
  → AuditService::log()              [record action]
  → releaseLock(bookingId)
  → JSON { success: true, message: "..." }
```

Same flow for check-out, with `canTransitionTo(CheckedOut)`.

### Route Map

| Route Name | Method | URI | Controller | Auth |
|------------|--------|-----|------------|------|
| `booking.check-in` | POST | `/booking/check-in/{bookingId}` | `guestCheckIn` | Token |
| `booking.check-out` | POST | `/booking/check-out/{bookingId}` | `guestCheckOut` | Token |
| `dashboard.my-bookings.check-in` | POST | `/dashboard/my-bookings/check-in/{bookingId}` | `loggedInCheckIn` | Session |
| `dashboard.my-bookings.check-out` | POST | `/dashboard/my-bookings/check-out/{bookingId}` | `loggedInCheckOut` | Session |
| `dashboard.bookings.guest.check-in` | POST | `/dashboard/bookings/guest/check-in/{bookingId}` | `guestCheckIn` | Admin |
| `dashboard.bookings.guest.check-out` | POST | `/dashboard/bookings/guest/check-out/{bookingId}` | `guestCheckOut` | Admin |

## Cancellation Flow

1. Guest clicks "Request Cancellation"
2. Email sent with confirmation link (configurable expiry)
3. Guest clicks link → `confirmCancellation()` processes the refund
4. Status transitions to `cancelled`

## Modification Flow

1. Guest submits modification form (dates, guests, notes)
2. Admin receives notification
3. Admin processes via `BookingController@update`

## API Endpoints

Public API routes for external integrations:

| Method | URI | Purpose |
|--------|-----|---------|
| POST | `api/booking/check-availability` | Check property availability |
| GET | `api/booking/cache-version/{propertyId}` | Get cache version for property |
| POST | `api/booking/modify/{bookingId}` | Request modification |
| POST | `api/booking/cancel/{bookingId}` | Request cancellation |

These are loaded via `loadPublicRoutes()` with `api` prefix.
