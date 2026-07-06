# Booking Module — Frontend

## Pages

All pages in `Resources/js/Pages/`:

| Page | Route | Purpose |
|------|-------|---------|
| `Index.tsx` | `/dashboard/bookings` | Admin booking list (DataTable, filters, bulk actions) |
| `Form.tsx` | `/dashboard/bookings/create`, `/dashboard/bookings/{booking}/edit` | Create/edit booking form |
| `Show.tsx` | `/dashboard/bookings/{booking}` | Booking detail view with timeline, actions |
| `Calendar.tsx` | `/dashboard/bookings/calendar` | Full calendar view with availability |
| `BookingDemo.tsx` | `/dashboard/bookings/demo` | Demo booking pipeline |
| `BookingDemoResult.tsx` | `/dashboard/bookings/demo/result/{booking}` | Demo pipeline result |
| `GuestView.tsx` | `/booking/show/{bookingId}` | Guest portal view (token-based) |
| `MyBookings.tsx` | `/dashboard/my-bookings` | Authenticated guest booking list |
| `MyBookingDetail.tsx` | `/dashboard/my-bookings/{bookingId}` | Authenticated guest booking detail |

## Key Components

| Component | Path | Purpose |
|-----------|------|---------|
| `PricingBreakdown` | `components/guest/PricingBreakdown.tsx` | Pricing details display |
| `BookingTimeline` | `components/guest/BookingTimeline.tsx` | Check-in/check-out timeline |
| `TransitionDialog` | `components/TransitionDialog.tsx` | Status transition dialog |
| `DashboardCard` | `components/DashboardCard.tsx` | Dashboard stats card |
| `DashboardCardSkeleton` | `components/DashboardCard.tsx` | Loading skeleton |

## Shared Library

| File | Purpose |
|------|---------|
| `lib/booking-status.ts` | Booking status constants, labels, badge styles, transition validation |

### booking-status.ts Constants

```typescript
export const BOOKING_STATUS = {
  Pending: "pending",
  Confirmed: "confirmed",
  CheckedIn: "checked_in",
  CheckedOut: "checked_out",
  Cancelled: "cancelled",
} as const;
```

Frontend transition validation (`canTransition()`):

| From | To |
|------|----|
| `Pending` | `Confirmed`, `Cancelled` |
| `Confirmed` | `CheckedIn`, `Cancelled` |
| `CheckedIn` | `CheckedOut`, `Cancelled` |
| `CheckedOut` | _(none)_ |
| `Cancelled` | _(none)_ |

## Guest Portal UI

### GuestView.tsx (Token-Based)

Renders when accessing `/booking/show/{bookingId}` with a valid token:

- Booking information card (timeline, pricing, guest info)
- Property card (thumbnail + link)
- Contact host card (phone, email, social)
- Create account / inline login cards
- **Check-In card** (when `Confirmed`) — "I've Arrived" button
- **Check-Out card** (when `CheckedIn`) — "I'm Leaving" button
- Modification request card
- Cancellation card with refund preview

### MyBookings.tsx (Authenticated)

DataTable with status filter tabs. Dropdown actions per row:

- View Details
- **Check In** (when `Confirmed`)
- **Check Out** (when `CheckedIn`)
- Cancel Booking

### MyBookingDetail.tsx (Authenticated)

Similar layout to GuestView but with session auth:

- Booking info, pricing, guest info
- Contact host card
- **Check-In** / **Check-Out** card
- Modification request card
- Cancellation card with refund preview

## Damage Photos

Uploaded via `BookingAjaxController@uploadDamagePhoto`, displayed in booking Show page. Config: max 20 images, `bookings/damage` upload path, hidden from media library.
