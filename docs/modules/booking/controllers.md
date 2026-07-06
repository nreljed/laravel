# Booking Module — Controllers

| Controller | File | Purpose |
|------------|------|---------|
| `BookingController` | `Controllers/BookingController.php` | Admin CRUD, calendar, availability, iCal, actions |
| `BookingGuestController` | `Controllers/BookingGuestController.php` | Guest portal, my-bookings, check-in/check-out |
| `BookingAvailabilityController` | `Controllers/BookingAvailabilityController.php` | Availability checks, blocking |
| `BookingDemoController` | `Controllers/BookingDemoController.php` | Demo booking pipeline |
| `BookingGlobalSettingsController` | `Controllers/BookingGlobalSettingsController.php` | Global settings CRUD |
| `BookingIcalController` | `Controllers/BookingIcalController.php` | iCal import/feeds/sync |
| `BookingSystemController` | `Controllers/BookingSystemController.php` | System transitions, status |
| `BookingAjaxController` | `Controllers/BookingAjaxController.php` | Damage photo upload/delete |

## BookingController

CRUD routes under `/dashboard/bookings`:

| Method | Route | Purpose | Access |
|--------|-------|---------|--------|
| `index()` | `GET /` | List bookings | `bookings.view` |
| `create()` | `GET /create` | Show create form | `bookings.create` |
| `store()` | `POST /` | Create booking | `bookings.create` |
| `edit()` | `GET /{booking}/edit` | Show edit form | `bookings.edit` |
| `update()` | `PUT /{booking}` | Update booking | `bookings.edit` |
| `show()` | `GET /{booking}` | View booking detail | `bookings.view` |
| `destroy()` | `DELETE /{booking}` | Delete booking | `bookings.delete` |
| `transition()` | `POST /{booking}/transition` | Status transition | `bookings.edit` |
| `bulkTransition()` | `POST /bulk` | Bulk transitions | `bookings.edit` |
| `exportCsv()` | `GET /export/csv` | CSV export | `bookings.view` |
| `calendar()` | `GET /calendar/data` | Calendar JSON data | `bookings.view` |
| `calculatePrice()` | `POST /calculate-price` | Price calculation | `bookings.create` |
| `cancel()` | `POST /{booking}/cancel` | Cancel booking | `bookings.edit` |
| `markPaid()` | `POST /{booking}/mark-paid` | Mark as paid | `bookings.edit` |
| `blockDates()` | `POST /block-dates` | Block date range | `bookings.edit` |
| `markDepositCollected()` | `POST /{booking}/deposit/collect` | Deposit collected | `bookings.edit` |
| `markDepositRefunded()` | `POST /{booking}/deposit/refund` | Deposit refunded | `bookings.edit` |
| `markDepositClaimed()` | `POST /{booking}/deposit/claim` | Deposit claimed | `bookings.edit` |
| `policyOverride()` | `POST /{booking}/policy-override` | Override policy | `bookings.edit` |
| `requestReview()` | `POST /{booking}/request-review` | Request review | `bookings.edit` |
| `toggleArchive()` | `POST /archive/{booking}` | Archive booking | `bookings.edit` |

> **Note:** Host profile resolution methods (`resolveHostPhone`, `resolveHostEmail`, etc.) were removed from this controller in Phase 18 and moved to `HostProfileService`.

## BookingGuestController

| Method | Route | Purpose | Auth |
|--------|-------|---------|------|
| `view()` | `GET /booking/view` | Guest view by token | Token |
| `show()` | `GET /booking/show/{bookingId}` | Show booking details | Token |
| `sendMagicLink()` | `POST /booking/send-magic-link` | Send magic link email | None |
| `inlineLogin()` | `POST /booking/inline-login` | Login with password | None |
| `registerFromBooking()` | `POST /booking/register` | Create account from booking | None |
| `requestCancellation()` | `POST /booking/cancel/{bookingId}` | Request cancellation | Token/None |
| `requestModification()` | `POST /booking/modify/{bookingId}` | Request modification | Token/None |
| `confirmCancellation()` | `GET /booking/confirm-cancel/{bookingId}` | Confirm via email link | Token |
| `guestCheckIn()` | `POST /booking/check-in/{bookingId}` | Check-in (token) | Token |
| `guestCheckOut()` | `POST /booking/check-out/{bookingId}` | Check-out (token) | Token |
| `myBookings()` | `GET /dashboard/my-bookings` | My bookings list | Auth |
| `loggedInCheckIn()` | `POST /dashboard/my-bookings/check-in/{bookingId}` | Check-in (auth) | Auth |
| `loggedInCheckOut()` | `POST /dashboard/my-bookings/check-out/{bookingId}` | Check-out (auth) | Auth |
| `loggedInCancel()` | `POST /dashboard/my-bookings/cancel/{bookingId}` | Cancel (auth) | Auth |
| `loggedInModify()` | `POST /dashboard/my-bookings/modify/{bookingId}` | Modify (auth) | Auth |

### Check-In/Check-Out Security

Both guest and logged-in check-in/out methods:
1. Acquire a per-booking lock (prevents concurrent requests)
2. Validate authorization (token or session)
3. Verify `canTransitionTo()` allows the transition
4. Perform the transition via `BookingStatusManager`
5. Log the action via `AuditService`
6. Return JSON `{ success: true, message: "..." }`

## BookingAvailabilityController

| Method | Route | Purpose |
|--------|-------|---------|
| `check()` | `GET /availability/{propertyId}` | Check availability |
| `block()` | `POST /availability/block` | Block dates |
| `unblock()` | `POST /availability/unblock/{id}` | Unblock dates |
| `checkAvailabilityApi()` | `POST /availability/check-api` | API availability check |
| `cacheVersion()` | `GET /availability/cache-version/{propertyId}` | Cache version |
| `clearOpcache()` | `POST /availability/clear-opcache` | Clear opcache |

## Other Controllers

| Controller | Key Methods |
|------------|-------------|
| `BookingDemoController` | `demo()`, `seed()`, `cleanupAll()`, `demoResult()`, `generateMagicLink()`, `tokenStatus()`, `emailStatus()`, `pipelineTrace()`, `verifyIntegration()`, `diagnose()` |
| `BookingGlobalSettingsController` | `edit()`, `update()` |
| `BookingIcalController` | `import()`, `feeds()`, `sync()`, `destroy()` |
| `BookingSystemController` | `runTransitions()`, `status()` |
| `BookingAjaxController` | `uploadDamagePhoto()`, `deleteDamagePhoto()` |
