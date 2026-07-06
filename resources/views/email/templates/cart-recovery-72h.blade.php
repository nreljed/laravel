<h1>Your cart is still waiting!</h1>
<p>Hi {{ $customerName ?? 'Valued Customer' }},</p>
<p>We noticed you haven't completed your purchase yet. Your cart on <strong>{{ $siteName }}</strong> is still saved with <strong>{{ $itemCount }}</strong> item(s).</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Items</td><td>{{ $itemCount }}</td></tr>
  <tr><td>Subtotal</td><td>{{ $subtotal }}</td></tr>
</table>
<p>Don't miss out! Your cart will be available for a limited time.</p>
<p class="text-center mt-4">
  <a href="{{ $cartUrl }}" class="button">Complete Your Purchase</a>
</p>