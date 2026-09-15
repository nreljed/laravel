<h1>Order confirmed</h1>
<p>Hi {{ $customerName ?? $userName ?? 'there' }},</p>
<p>Thank you for your order on <strong>{{ $siteName }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Order number</td><td>#{{ $orderNumber ?? $notificationTitle ?? '' }}</td></tr>
  <tr><td>Total</td><td>{{ $orderTotal ?? '' }}</td></tr>
  <tr><td>Status</td><td>{{ $orderStatus ?? $newStatus ?? '' }}</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $orderUrl ?? $notificationLink ?? '#' }}" class="button">View Order</a>
</p>
@if(!empty($createAccountUrl))
<p class="text-center mt-4" style="padding-top:12px; border-top:1px solid #e5e7eb;">
  <a href="{{ $createAccountUrl }}" class="button button-secondary" style="background:#6b7280;">Create Account &amp; Track Orders</a>
</p>
<p style="font-size:12px; color:#9ca3af; text-align:center; margin-top:8px;">
  Create an account to track your order, view order history, and manage your profile.
</p>
@endif
