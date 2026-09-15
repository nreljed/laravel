<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Services\AbandonedCartService;

class AbandonedCartRecoveryController extends Controller
{
    public function __construct(
        protected AbandonedCartService $abandonedCartService,
    ) {}

    public function recover(Request $request, Cart $cart)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Invalid or expired recovery link.');
        }

        if ($cart->converted_to_order_id) {
            return redirect()->route('dashboard.shop.orders.edit', $cart->converted_to_order_id)
                ->with('flash', ['success' => 'Your cart was already converted to an order!']);
        }

        $redirect = $this->abandonedCartService->handleRecovery($cart);

        return redirect($redirect)
            ->with('flash', ['success' => 'Welcome back! Your cart items are still here.']);
    }
}
