<h1>Low stock alert</h1>
<p>The following product is running low on stock on <strong>{{ $siteName }}</strong>:</p>
<table class="details-table" cellpadding="0" cellspacing="0" role="presentation">
  <tr><td>Product</td><td>{{ $productName ?? '' }}</td></tr>
  <tr><td>SKU</td><td>{{ $productSku ?? '' }}</td></tr>
  <tr><td>Stock</td><td>{{ $stockQuantity ?? 0 }} remaining</td></tr>
</table>
<p class="text-center mt-4">
  <a href="{{ $productUrl ?? '#' }}" class="button">View Product</a>
</p>
