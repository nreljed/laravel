<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $order->order_number }}</title>
    <style>
        body { font-family: system-ui, sans-serif; font-size: 14px; color: #1a1a1a; margin: 0; padding: 0; }
        .sheet { max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        .header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 40px; }
        h1 { margin: 0; font-size: 28px; color: #111; }
        .status { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e5e5; }
        th { font-size: 12px; text-transform: uppercase; color: #666; }
        .amount { text-align: right; }
        .total-row td { font-weight: 600; border-top: 2px solid #1a1a1a; }
        .addresses { display: flex; gap: 40px; margin-bottom: 40px; }
        .addr { flex: 1; }
        .addr h3 { font-size: 13px; text-transform: uppercase; color: #666; margin-bottom: 8px; }
        .addr p { margin: 2px 0; line-height: 1.5; }
        .footer { margin-top: 40px; font-size: 12px; color: #999; text-align: center; }
        @media print { body { -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="header">
            <div>
                <h1>INVOICE</h1>
                <p class="status">{{ $order->status ?? 'pending' }} — {{ $order->payment_status ?? 'pending' }}</p>
            </div>
            <div style="text-align: right;">
                <p><strong>{{ $siteName ?? 'Store' }}</strong></p>
                <p>#{{ $order->order_number }}</p>
                <p>{{ $order->created_at?->format('M d, Y') }}</p>
            </div>
        </div>

        <div class="addresses">
            <div class="addr">
                <h3>Bill To</h3>
                <p>{{ $order->billing_address['first_name'] ?? '' }} {{ $order->billing_address['last_name'] ?? '' }}</p>
                <p>{{ $order->billing_address['address_line1'] ?? '' }}</p>
                @if (! empty($order->billing_address['address_line2']))<p>{{ $order->billing_address['address_line2'] }}</p>@endif
                <p>{{ $order->billing_address['city'] ?? '' }}, {{ $order->billing_address['state'] ?? '' }} {{ $order->billing_address['postcode'] ?? '' }}</p>
                <p>{{ $order->billing_address['country'] ?? '' }}</p>
            </div>
            <div class="addr">
                <h3>Ship To</h3>
                <p>{{ $order->shipping_address['first_name'] ?? '' }} {{ $order->shipping_address['last_name'] ?? '' }}</p>
                <p>{{ $order->shipping_address['address_line1'] ?? '' }}</p>
                @if (! empty($order->shipping_address['address_line2']))<p>{{ $order->shipping_address['address_line2'] }}</p>@endif
                <p>{{ $order->shipping_address['city'] ?? '' }}, {{ $order->shipping_address['state'] ?? '' }} {{ $order->shipping_address['postcode'] ?? '' }}</p>
                <p>{{ $order->shipping_address['country'] ?? '' }}</p>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>Item</th><th>SKU</th><th style="text-align: center;">Qty</th><th class="amount">Unit Price</th><th class="amount">Total</th></tr>
            </thead>
            <tbody>
                @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->product_sku ?? '—' }}</td>
                    <td style="text-align: center;">{{ $item->quantity }}</td>
                    <td class="amount">{{ number_format((float) $item->unit_price, 2) }} {{ $order->currency ?? 'USD' }}</td>
                    <td class="amount">{{ number_format((float) $item->total, 2) }} {{ $order->currency ?? 'USD' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table>
            <tr><td>Subtotal</td><td class="amount">{{ number_format((float) $order->subtotal, 2) }} {{ $order->currency ?? 'USD' }}</td></tr>
            @if ((float) ($order->discount_total ?? 0) > 0)<tr><td>Discount</td><td class="amount">-{{ number_format((float) $order->discount_total, 2) }} {{ $order->currency ?? 'USD' }}</td></tr>@endif
            @if ((float) ($order->shipping_total ?? 0) > 0)<tr><td>Shipping</td><td class="amount">{{ number_format((float) $order->shipping_total, 2) }} {{ $order->currency ?? 'USD' }}</td></tr>@endif
            @if ((float) ($order->tax_total ?? 0) > 0)<tr><td>Tax</td><td class="amount">{{ number_format((float) $order->tax_total, 2) }} {{ $order->currency ?? 'USD' }}</td></tr>@endif
            <tr class="total-row"><td><strong>Total</strong></td><td class="amount"><strong>{{ number_format((float) $order->total, 2) }} {{ $order->currency ?? 'USD' }}</strong></td></tr>
        </table>

        @if ($order->notes)
        <div style="margin-top: 20px; padding: 16px; background: #f5f5f5; border-radius: 6px;">
            <p style="font-size: 12px; color: #666; margin: 0 0 4px;">Notes</p>
            <p style="margin: 0;">{{ $order->notes }}</p>
        </div>
        @endif

        <div class="footer">
            <p>{{ $siteName ?? 'Store' }} — Thank you for your business!</p>
        </div>
    </div>
</body>
</html>
