<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Cart;
use App\Modules\Shop\Services\AbandonedCartService;
use Illuminate\Http\Request;

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
