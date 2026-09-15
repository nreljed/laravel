<h1>Booking status updated</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>Your booking at <strong>{{ $propertyTitle ?? 'Property' }}</strong> has been updated.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Previous status</td><td>{{ $oldStatus ?? '' }}</td></tr>
  <tr><td>New status</td><td><strong>{{ $newStatus ?? '' }}</strong></td></tr>
  <tr><td>Check-in</td><td>{{ $checkIn ?? '' }}</td></tr>
  <tr><td>Check-out</td><td>{{ $checkOut ?? '' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $bookingUrl ?? '#' }}" class="button">View Booking</a>
</p>
