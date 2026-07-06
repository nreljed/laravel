<h1>You left items in your cart!</h1>
<p>Hi {{ $customerName ?? 'Valued Customer' }},</p>
<p>You have <strong>{{ $itemCount }}</strong> item(s) in your cart on <strong>{{ $siteName }}</strong>.</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Items</td><td>{{ $itemCount }}</td></tr>
  <tr><td>Subtotal</td><td>{{ $subtotal }}</td></tr>
</table>
<p>Your cart is saved and ready when you are.</p>
<p class="text-center mt-4">
  <a href="{{ $cartUrl }}" class="button">Complete Your Order</a>
</p>