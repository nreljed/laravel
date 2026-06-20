<h1>Booking Refund Summary</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>A refund has been issued for your booking at <strong>{{ $propertyTitle ?? 'Property' }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Property</td><td>{{ $propertyTitle ?? '' }}</td></tr>
  <tr><td>Check-in</td><td>{{ $checkIn ?? '' }}</td></tr>
  <tr><td>Check-out</td><td>{{ $checkOut ?? '' }}</td></tr>
  <tr><td>Policy Applied</td><td>{{ $policyApplied ?? '' }}</td></tr>
  <tr><td>Refund Percentage</td><td>{{ $refundPercent ?? 0 }}%</td></tr>
  <tr><td>Refund Amount</td><td>{{ $refundAmount ?? '' }}</td></tr>
</table>
@if(!empty($reason))
<p><strong>Reason:</strong> {{ $reason }}</p>
@endif
<p>If you have any questions, please contact us.</p>
