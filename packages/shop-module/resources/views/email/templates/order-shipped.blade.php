<h1>Order #{{ $orderNumber ?? '' }} — {{ $newStatus ?? 'Status Changed' }}</h1>
<p>Hi {{ $customerName ?? 'there' }},</p>
<p>The status of your order on <strong>{{ $siteName }}</strong> has changed.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Order number</td><td>#{{ $orderNumber ?? '' }}</td></tr>
  <tr><td>New status</td><td>{{ $newStatus ?? $orderStatus ?? '' }}</td></tr>
  @if(!empty($trackingNumber))
  <tr><td>Tracking number</td><td>{{ $trackingNumber }}</td></tr>
  @endif
</table>
<p class="text-center mt-4">
  <a href="{{ $orderUrl ?? '#' }}" class="button">View Order</a>
</p>
