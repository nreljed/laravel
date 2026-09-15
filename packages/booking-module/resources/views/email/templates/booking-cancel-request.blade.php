<h1>Confirm your cancellation request</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>You have requested to cancel your booking at <strong>{{ $propertyTitle ?? 'Property' }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Property</td><td>{{ $propertyTitle ?? '' }}</td></tr>
  <tr><td>Check-in</td><td>{{ $checkIn ?? '' }}</td></tr>
  <tr><td>Check-out</td><td>{{ $checkOut ?? '' }}</td></tr>
</table>
<p>To confirm your cancellation, click the button below:</p>
<p class="text-center mt-4">
  <a href="{{ $cancelUrl ?? '#' }}" class="button">Confirm Cancellation</a>
</p>
<p>This link expires in {{ $expiryMinutes ?? 30 }} minutes. If you did not request cancellation, you can ignore this email.</p>
