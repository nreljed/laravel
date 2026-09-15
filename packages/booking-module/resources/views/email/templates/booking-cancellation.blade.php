<h1>Booking cancelled</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>Your booking at <strong>{{ $propertyTitle ?? 'Property' }}</strong> has been cancelled.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Property</td><td>{{ $propertyTitle ?? '' }}</td></tr>
  <tr><td>Check-in</td><td>{{ $checkIn ?? '' }}</td></tr>
  <tr><td>Check-out</td><td>{{ $checkOut ?? '' }}</td></tr>
</table>
@if(!empty($refundInfo))
<p><strong>Refund:</strong> {{ $refundInfo }}</p>
@endif
<p>If you have any questions, please contact us.</p>
