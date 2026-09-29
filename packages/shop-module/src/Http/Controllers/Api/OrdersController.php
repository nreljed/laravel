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

    public function show(Request $request, Order $order)
    {
        // Without this the route bound any order off the URL, so any sanctum
        // user could read any other customer's order - name, email, billing and
        // shipping addresses, item lines - by incrementing the id. `index()`
        // already scopes to the caller, so `show` answers the same question.
        $this->authorize('viewOwn', $order);

        $order->load('items');

        return new OrderResource($order);
    }
}
