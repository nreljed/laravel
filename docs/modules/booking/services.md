# Booking Module — Services

| Service | Purpose | Key Methods |
|---------|---------|-------------|
| `BookingStatusManager` | Status transition logic | `transition(booking, targetStatus, reason)` |
| `BookingCacheService` | Cache busting for property data | `bustPropertyCache(propertyId)` |
| `BookingEmailService` | Guest-facing email sending | `sendConfirmation()`, `sendCancellation()`, `sendBookingReceived()`, `handleStatusTransition()` |
| `BookingGuestTokenService` | Magic link token management | `generateToken()`, `validateToken()`, `revokeTokens()`, `getMagicLink()` |
| `BookingPipelineTrace` | Debug trace during lifecycle | `add(bookingId, stage, message, data?)`, `clear(bookingId)` |
| `BookingRefundMailService` | Refund notification emails | `sendRefundNotification(bookingId, refundData)` |
| `BookingActionService` | Audit log for booking actions | `recordAction(bookingId, action, performedBy, metadata)`, `getActions(bookingId)` |
| `BookingActivityDashboardService` | Admin activity feed | `getRecentActivity(limit)`, `getActivityByBooking(bookingId)` |
| `BookingHistoryService` | Booking history/audit trail | `getHistory(bookingId)`, `getTimeline(bookingId)` |
| `BookingPropertyIntegrationService` | Bridge between Booking and Properties | `isModuleActive()`, `savePropertyBookingData()`, `getPropertyBookingData()` |
| `HostProfileService` | Host user data resolution | `resolveHostPhone()`, `resolveHostEmail()`, `resolveHostAvatar()`, `resolveHostBio()`, `resolveHostSocialMedia()`, `resolveAll(hostId)` |

## Key Service Details

### BookingStatusManager

```php
BookingStatusManager::transition(Booking $booking, BookingStatus $target, string $reason): void
```

- Validates `$booking->status->canTransitionTo($target)`
- Updates status and sets relevant timestamps (`checked_in_at`, `checked_out_at`, `cancelled_at`)
- Fires `booking.status-transition` event
- Records action via `BookingActionService`

### BookingGuestTokenService

Token is a signed hash of `bookingId + guestEmail + secret`. Stored in `booking_tokens` table:

```php
$rawToken = BookingGuestTokenService::generateToken($bookingId, $guestEmail);
$magicLink = BookingGuestTokenService::getMagicLink($bookingId, $rawToken);
// Validate:
$valid = BookingGuestTokenService::validateToken($bookingId, $rawToken);
```

Tokens are single-use (revoked after first validation) and expire after configurable duration.

### BookingActionService

Records every booking action for audit trail:

```php
BookingActionService::recordAction(
    bookingId: 1,
    action: 'check_in',
    performedBy: 'guest',
    metadata: ['property_id' => 5, 'timestamp' => '...']
);
```

Used by `BookingGuestController` (check-in/check-out), `BookingStatusManager` (transitions), and admin controllers.

### HostProfileService

Extracts host (property owner) profile data for display on booking detail pages. Replaces previously static methods on `BookingController`:

```php
$profile = $hostProfileService->resolveAll($hostId);
// Returns: [
//     'host_phone' => '+216...',
//     'host_phone_numbers' => [...],
//     'host_email' => '...',
//     'host_avatar' => 'https://...',
//     'host_bio' => '...',
//     'host_social_media' => [...],
// ]
```

Individual methods available for targeted resolution:

```php
$hostProfileService->resolveHostPhone($hostId);
$hostProfileService->resolveHostEmail($hostId);
$hostProfileService->resolveHostAvatar($hostId);
$hostProfileService->resolveHostBio($hostId);
$hostProfileService->resolveHostSocialMedia($hostId);
$hostProfileService->resolveHostPhoneNumbers($hostId);
```

Injected into `BookingController` and `BookingGuestController` via constructor DI. Eliminates cross-controller static dependency (previously `BookingGuestController` called `BookingController::resolveHost*()`).
