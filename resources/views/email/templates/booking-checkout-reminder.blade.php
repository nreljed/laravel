<h1>Check-out tomorrow</h1>
<p>Hi {{ $guestName ?? 'there' }},</p>
<p>We hope you're enjoying your stay at <strong>{{ $propertyTitle ?? 'Property' }}</strong>.</p>
<p>This is a friendly reminder that check-out is <strong>tomorrow by {{ $checkOutTime ?? '11:00' }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Booking ID</td><td>#{{ $bookingId ?? '' }}</td></tr>
  <tr><td>Property</td><td>{{ $propertyTitle ?? '' }}</td></tr>
  <tr><td>Check-out date</td><td>{{ $checkOut ?? '' }}</td></tr>
</table>
<h2>Before you leave</h2>
<ul>
  <li>Please gather all your belongings</li>
  <li>Lock all doors and windows</li>
  <li>Leave the keys where instructed</li>
</ul>
@if(!empty($checkOutInstructions))
<p><strong>Check-out instructions:</strong> {{ $checkOutInstructions }}</p>
@endif
<p class="text-center mt-4">
  <a href="{{ $bookingUrl ?? '#' }}" class="button">View Booking Details</a>
</p>
<p>Thank you for staying with us!</p>
