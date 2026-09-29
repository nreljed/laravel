<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Order;

class MyOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => (float) $order->total,
                'items_count' => (int) $order->items_count,
                'created_at' => $order->created_at->toISOString(),
                'created_at_relative' => $order->created_at->diffForHumans(),
            ]);

        return Inertia::render('shop/my/orders/index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $this->authorize('viewOwn', $order);

        $order->load(['items', 'actions' => fn ($q) => $q->latest()]);

        return Inertia::render('shop/my/orders/show', [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'subtotal' => (float) $order->subtotal,
                'discount_total' => (float) $order->discount_total,
                'shipping_total' => (float) $order->shipping_total,
                'tax_total' => (float) $order->tax_total,
                'total' => (float) $order->total,
                'payment_method' => $order->payment_method,
                'payment_status' => $order->payment_status,
                'billing_address' => $order->billing_address,
                'shipping_address' => $order->shipping_address,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'paid_at' => $order->paid_at?->toISOString(),
                'shipped_at' => $order->shipped_at?->toISOString(),
                'created_at' => $order->created_at->toISOString(),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'unit_price' => (float) $item->unit_price,
                    'quantity' => (int) $item->quantity,
                    'total' => (float) $item->total,
                    'image_url' => $item->image_url,
                    'variant_label' => $item->variant_label,
                ]),
                'actions' => $order->actions->map(fn ($a) => [
                    'id' => $a->id,
                    'action' => $a->action,
                    'description' => $a->description,
                    'created_at' => $a->created_at->toISOString(),
                ]),
            ],
        ]);
    }
}
