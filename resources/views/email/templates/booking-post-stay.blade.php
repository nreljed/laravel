<h1>How was your stay?</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>We hope you enjoyed your stay at <strong>{{ $propertyTitle ?? 'Property' }}</strong>.</p>
<p>We'd love to hear about your experience. Your feedback helps us improve and helps other guests make informed decisions.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Check-in</td><td>{{ $checkIn ?? '' }}</td></tr>
  <tr><td>Check-out</td><td>{{ $checkOut ?? '' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $bookingUrl ?? '#' }}" class="button">Leave a Review</a>
</p>
<p>Thank you for choosing us!</p>
