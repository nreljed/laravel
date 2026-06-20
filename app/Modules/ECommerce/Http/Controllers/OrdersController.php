<?php

namespace App\Modules\ECommerce\Http\Controllers;

use App\Modules\ECommerce\Models\Order;
use App\Modules\ECommerce\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrdersController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['items', 'user']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%' . $request->search . '%')
                    ->orWhere('customer_name', 'like', '%' . $request->search . '%')
                    ->orWhere('customer_email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('ECommerce/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['items.product', 'user']);

        return Inertia::render('ECommerce/Orders/Show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,confirmed,processing,completed,cancelled,refunded',
            'notes' => 'nullable|string|max:1000',
        ]);

        $order->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $order->notes,
        ]);

        if ($validated['status'] === 'completed' && !$order->paid_at) {
            $order->update(['paid_at' => now(), 'payment_status' => 'paid']);
        }

        return redirect()->back()->with('status', 'Order status updated.');
    }
}
