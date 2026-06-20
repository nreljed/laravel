<h1>Booking confirmed!</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>Your booking at <strong>{{ $propertyTitle ?? 'Property' }}</strong> has been confirmed.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Property</td><td>{{ $propertyTitle ?? '' }}</td></tr>
  <tr><td>Check-in</td><td>{{ $checkIn ?? '' }}</td></tr>
  <tr><td>Check-out</td><td>{{ $checkOut ?? '' }}</td></tr>
  <tr><td>Guests</td><td>{{ $guests ?? '' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $bookingUrl ?? '#' }}" class="button">View Booking</a>
</p>
