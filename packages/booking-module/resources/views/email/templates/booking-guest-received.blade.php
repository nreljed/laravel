<h1>Booking received!</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>Your booking at <strong>{{ $propertyTitle ?? 'Property' }}</strong> has been received and is being processed.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Property</td><td>{{ $propertyTitle ?? '' }}</td></tr>
  <tr><td>Check-in</td><td>{{ $checkIn ?? '' }} at {{ $checkInTime ?? '15:00' }}</td></tr>
  <tr><td>Check-out</td><td>{{ $checkOut ?? '' }} at {{ $checkOutTime ?? '11:00' }}</td></tr>
  <tr><td>Guests</td><td>{{ $guests ?? '' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $magicLink ?? '#' }}" class="button">View Your Booking</a>
</p>
<p>This link will be valid for {{ $expiresInDays ?? 30 }} days.</p>
