<h1>Still thinking about it?</h1>
<p>Hi {{ $customerName ?? 'Valued Customer' }},</p>
<p>Just a friendly reminder that you still have items waiting in your cart on <strong>{{ $siteName }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Items</td><td>{{ $itemCount }}</td></tr>
  <tr><td>Subtotal</td><td>{{ $subtotal }}</td></tr>
</table>
<p>If you have any questions about the products, feel free to reach out.</p>
<p class="text-center mt-4">
  <a href="{{ $cartUrl }}" class="button">Return to Cart</a>
</p>