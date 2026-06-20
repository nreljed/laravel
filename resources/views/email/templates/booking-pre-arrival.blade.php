<h1>You're arriving soon!</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>We're looking forward to hosting you at <strong>{{ $propertyTitle ?? 'Property' }}</strong>.</p>
<p>Here's a quick reminder of your upcoming stay:</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Property</td><td>{{ $propertyTitle ?? '' }}</td></tr>
  <tr><td>Check-in</td><td>{{ $checkIn ?? '' }} at {{ $checkInTime ?? '15:00' }}</td></tr>
  <tr><td>Check-out</td><td>{{ $checkOut ?? '' }} at {{ $checkOutTime ?? '11:00' }}</td></tr>
  <tr><td>Guests</td><td>{{ $guests ?? '' }}</td></tr>
</table>
@if(!empty($specialRequests))
<p><strong>Special requests:</strong> {{ $specialRequests }}</p>
@endif
<p class="text-center mt-4">
  <a href="{{ $bookingUrl ?? '#' }}" class="button">View Booking Details</a>
</p>
<p>If you have any questions before your stay, please don't hesitate to reach out.</p>
