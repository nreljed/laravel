<h1>Order confirmed</h1>
<p>Hi {{ $customerName ?? 'there' }},</p>
<p>Thank you for your order on <strong>{{ $siteName }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Order number</td><td>#{{ $orderNumber ?? '' }}</td></tr>
  <tr><td>Total</td><td>{{ $orderTotal ?? '' }}</td></tr>
  <tr><td>Status</td><td>{{ $orderStatus ?? '' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $orderUrl ?? '#' }}" class="button">View Order</a>
</p>
