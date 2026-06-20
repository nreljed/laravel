<h1>Order shipped</h1>
<p>Hi {{ $customerName ?? 'there' }},</p>
<p>Your order on <strong>{{ $siteName }}</strong> has been shipped.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Order number</td><td>#{{ $orderNumber ?? '' }}</td></tr>
  <tr><td>Tracking number</td><td>{{ $trackingNumber ?? 'N/A' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $orderUrl ?? '#' }}" class="button">View Order</a>
</p>
