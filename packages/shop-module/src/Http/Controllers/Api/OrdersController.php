<?php

namespace Nreljed\ShopModule\Http\Controllers\Api;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Http\Resources\OrderResource;
use Nreljed\ShopModule\Models\Order;

class OrdersController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }

        $perPage = min((int) ($request->per_page ?? 20), 100);
        $orders = $query->with('items')->orderBy('created_at', 'desc')->paginate($perPage);

        return OrderResource::collection($orders);
    }

    public function show(Order $order)
    {
        $order->load('items');

        return new OrderResource($order);
    }
}
