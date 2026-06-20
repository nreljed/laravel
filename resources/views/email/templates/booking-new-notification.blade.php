<h1>New Booking</h1>
<p>A new booking has been made at <strong>{{ $propertyTitle ?? 'Property' }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Guest</td><td>{{ $guestName ?? '' }}</td></tr>
  <tr><td>Email</td><td>{{ $guestEmail ?? '' }}</td></tr>
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $bookingUrl ?? '#' }}" class="button">View Booking</a>
</p>
