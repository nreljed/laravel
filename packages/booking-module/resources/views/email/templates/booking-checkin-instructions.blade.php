<h1>Welcome — check-in instructions</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>Welcome to <strong>{{ $propertyTitle ?? 'Property' }}</strong>! Your stay starts today.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Check-in time</td><td>{{ $checkInTime ?? '15:00' }}</td></tr>
  <tr><td>Check-out time</td><td>{{ $checkOutTime ?? '11:00' }} ({{ $checkOut ?? '' }})</td></tr>
</table>
@if(!empty($checkInInstructions))
<h2>Check-in Instructions</h2>
<p>{{ $checkInInstructions }}</p>
@endif
<p class="text-center mt-4">
  <a href="{{ $bookingUrl ?? '#' }}" class="button">View Booking Details</a>
</p>
<p>We hope you have a wonderful stay!</p>
