<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\Role;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Notifications\AppNotification;
use Nreljed\Platform\Services\MailService;
use Nreljed\Platform\Services\NotificationService;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Coupon;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderItem;
use Nreljed\ShopModule\Services\CartMergeService;
use Nreljed\ShopModule\Services\CheckoutService;
use Nreljed\ShopModule\Services\CouponService;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\ShippingService;
use Nreljed\ShopModule\Services\ShopSettingsService;
use Nreljed\ShopModule\Services\StripeService;
use Nreljed\ShopModule\Services\TaxService;

class CheckoutController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected MailService $mailService,
        protected CouponService $couponService,
        protected ShippingService $shippingService,
        protected TaxService $taxService,
        protected StripeService $stripeService,
        protected CurrencyService $currencyService,
        protected CartMergeService $cartMergeService,
        protected CheckoutService $checkoutService,
        protected ShopSettingsService $shopSettings,
    ) {}

    public function index(Request $request)
    {
        if (! $request->user() && ! $this->shopSettings->guestCheckoutEnabled()) {
            return redirect()->route('login')->with('status', 'Please sign in to checkout.');
        }

        $cart = $this->getCart($request);
        $cart->load('items.product', 'items.variation');

        if ($cart->items->isEmpty()) {
            $route = $request->user()
                ? 'dashboard.my.cart.index'
                : 'store.cart.index';

            return redirect()->route($route)->with('status', 'Your cart is empty.');
        }

        $user = $request->user();
        $billingAddress = $user?->billing_address ?? $cart->billing_address;
        $shippingAddress = $user?->shipping_address ?? $cart->shipping_address;

        $shippingMethods = $this->shippingService->getAvailableMethods($billingAddress);

        $defaultCurrency = $this->currencyService->getDefaultCurrency();

        // Effective shipping total for the default (pre-selected) method, mirroring
        // CheckoutService::processCheckout: apply free_above, then free_shipping coupons.
        $selectedShipping = $shippingMethods->first();
        $shippingTotal = $selectedShipping ? (float) $selectedShipping->price : 0;
        $freeAbove = $selectedShipping?->free_above;
        if ($freeAbove !== null && (float) $cart->subtotal >= (float) $freeAbove) {
            $shippingTotal = 0;
        }

        $freeShippingApplied = false;

        // Fold eligible coupons (applied + auto-discounts) into the displayed
        // totals so the checkout summary matches what checkout actually charges.
        if ($cart->items->isNotEmpty()) {
            $appliedIds = collect($cart->applied_coupons ?? [])
                ->pluck('coupon_id')
                ->filter()
                ->unique();
            $appliedModels = Coupon::whereIn('id', $appliedIds)->get();
            $eligible = $appliedModels->merge(
                $this->couponService->getAutoDiscounts($cart->subtotal)
                    ->filter(fn ($c) => ! $appliedModels->contains('id', $c->id))
            );

            if ($eligible->isNotEmpty()) {
                $stack = $this->couponService->calculateStackedDiscount(
                    $eligible, $cart->subtotal, $shippingTotal, $cart->items
                );

                $cart->discount = $stack['total_discount'];

                foreach ($stack['breakdown'] as $b) {
                    if ($b['type'] === 'free_shipping') {
                        $freeShippingApplied = true;
                        $shippingTotal = 0;
                    }
                }
            }
        }

        $taxResult = $this->taxService->calculate(
            (float) $cart->subtotal,
            $billingAddress,
            $shippingAddress,
            $shippingTotal,
        );

        return Inertia::render('shop/orders/checkout', [
            'cart' => $cart,
            'shippingMethods' => $shippingMethods,
            'userBillingAddress' => $billingAddress,
            'userShippingAddress' => $shippingAddress,
            'stripeKey' => $this->stripeService->getPublishableKey(),
            'currency' => $defaultCurrency?->code ?? 'USD',
            'estimatedTax' => $taxResult,
            'freeShippingThreshold' => $shippingMethods->min('free_above'),
            'freeShippingApplied' => $freeShippingApplied,
        ]);
    }

    public function confirmation(Request $request, Order $order)
    {
        if ($order->user_id && (! $request->user() || $order->user_id !== $request->user()->id)) {
            abort(403);
        }

        $order->load('items');

        return Inertia::render('shop/orders/confirmation', [
            'order' => $order,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user && ! $this->shopSettings->guestCheckoutEnabled()) {
            throw ValidationException::withMessages([
                'customer_email' => 'Guest checkout is disabled. Please sign in to place your order.',
            ]);
        }

        $rules = [
            'payment_method' => ['required', 'string', 'in:manual,stripe'],
            'stripe_payment_intent_id' => ['nullable', 'required_if:payment_method,stripe', 'string'],
            'shipping_method_id' => ['required', 'exists:shipping_methods,id'],
            'customer_email' => $user ? ['nullable'] : ['required', 'email', 'max:255'],
            'customer_name' => $user ? ['nullable'] : ['required', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'billing_address' => ['required', 'array'],
            'billing_address.first_name' => ['required', 'string', 'max:255'],
            'billing_address.last_name' => ['required', 'string', 'max:255'],
            'billing_address.address_line1' => ['required', 'string', 'max:255'],
            'billing_address.city' => ['required', 'string', 'max:255'],
            'billing_address.country' => ['required', 'string', 'size:2'],
            'billing_address.postcode' => ['nullable', 'string', 'max:20'],
            'billing_address.state' => ['nullable', 'string', 'max:255'],
            'billing_address.company' => ['nullable', 'string', 'max:255'],
            'billing_address.address_line2' => ['nullable', 'string', 'max:255'],
            'billing_address.phone' => ['nullable', 'string', 'max:50'],
            'shipping_address' => ['required', 'array'],
            'shipping_address.first_name' => ['required', 'string', 'max:255'],
            'shipping_address.last_name' => ['required', 'string', 'max:255'],
            'shipping_address.address_line1' => ['required', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:255'],
            'shipping_address.country' => ['required', 'string', 'size:2'],
            'shipping_address.postcode' => ['nullable', 'string', 'max:20'],
            'shipping_address.state' => ['nullable', 'string', 'max:255'],
            'shipping_address.company' => ['nullable', 'string', 'max:255'],
            'shipping_address.address_line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.phone' => ['nullable', 'string', 'max:50'],
        ];

        if ($this->shopSettings->requireCompanyField()) {
            $rules['billing_address.company'] = ['required', 'string', 'max:255'];
        }

        if ($this->shopSettings->requireTerms()) {
            $rules['accepted_terms'] = ['required', 'accepted'];
        }

        $validated = $request->validate($rules);

        // Enforce selling locations policy on both addresses.
        foreach (['billing_address', 'shipping_address'] as $addressKey) {
            $country = $validated[$addressKey]['country'] ?? null;
            if (! $this->shopSettings->isCountryAllowed($country)) {
                throw ValidationException::withMessages([
                    $addressKey.'.country' => 'We are not currently selling to this country.',
                ]);
            }
        }

        $cart = $this->getCart($request);
        $cart->load('items.product', 'items.variation');

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        // Create account if guest provided a password
        if (! $user && ! empty($validated['password'])) {
            $email = $validated['customer_email'];
            $existingUser = User::where('email', $email)->first();

            if ($existingUser) {
                $user = $existingUser;
                if (! Hash::check($validated['password'], $user->password)) {
                    throw ValidationException::withMessages([
                        'email' => 'An account with this email already exists. Please sign in instead.',
                    ]);
                }
            } else {
                $user = User::create([
                    'username' => Str::before($email, '@').'_'.Str::random(4),
                    'name' => $validated['customer_name'],
                    'email' => $email,
                    'password' => Hash::make($validated['password']),
                ]);

                $customerRole = Role::where('slug', 'customer')->where('slug', '!=', 'admin')->first();
                if ($customerRole) {
                    $user->roles()->attach($customerRole->id);
                }

                event(new Registered($user));
                Auth::login($user);
            }
        }

        $order = $this->checkoutService->processCheckout($cart, $validated, $user);

        // Merge any lingering session cart into user cart
        if ($user) {
            $this->cartMergeService->mergeGuestCartIntoUserCart($user);
        }

        $notificationData = [
            'title' => 'New Order #'.$order->order_number,
            'message' => 'Order '.$order->order_number.' has been placed successfully.',
            'module' => 'Shop',
            'link' => route('dashboard.shop.orders.show', $order->id, false),
            'orderId' => $order->id,
            'orderNumber' => $order->order_number,
            'orderTotal' => number_format((float) $order->total, 2).' EUR',
            'orderStatus' => ucfirst($order->status),
            'orderUrl' => route('dashboard.shop.orders.show', $order->id, false),
            'customerName' => $order->customer_name,
        ];

        if ($user) {
            $notification = new AppNotification('order.placed', $notificationData);
            $this->notificationService->notifyViaPreferences($user, 'order.placed', $notification);
        } else {
            $registerUrl = URL::temporarySignedRoute(
                'store.checkout.register-from-order',
                now()->addDays(7),
                ['order' => $order->id, 'email' => $order->customer_email],
            );

            $notificationData['createAccountUrl'] = $registerUrl;

            $this->mailService->send(
                $order->customer_email,
                '['.config('app.name').'] Order Confirmed — #'.$order->order_number,
                'order-confirmation',
                $notificationData,
            );
        }

        // Determine redirect route based on origin
        $redirectRoute = $this->resolveRedirectRoute($request, $user);

        return redirect()->route($redirectRoute, $order->id)
            ->with('flash', ['success' => 'Order placed successfully!']);
    }

    public function createPaymentIntent(Request $request)
    {
        $cart = $this->getCart($request);
        $cart->load('items.product', 'items.variation');

        if ($cart->items->isEmpty()) {
            return response()->json(['error' => 'Cart is empty.'], 400);
        }

        $user = $request->user();

        $order = DB::transaction(function () use ($cart, $user) {
            $subtotal = $cart->subtotal;
            $shippingMethod = null;
            $shippingTotal = 0;

            $order = Order::create([
                'user_id' => $user?->id,
                'customer_email' => $user?->email ?? 'pending@checkout.com',
                'customer_name' => $user?->name ?? 'Pending',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'status' => 'pending',
                'payment_method' => 'stripe',
                'payment_status' => 'pending',
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_variation_id' => $item->product_variation_id,
                    'product_sku' => $item->variation?->sku ?? $item->product->sku,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->unit_price * $item->quantity,
                    'total' => $item->unit_price * $item->quantity,
                    'variant_label' => $item->variant_label,
                    'image_url' => $item->variation?->image_url ?? $item->product->thumbnail_url,
                ]);
            }

            return $order;
        });

        $currency = $this->currencyService->getDefaultCurrency();

        try {
            $intent = $this->stripeService->createPaymentIntent($order, $currency?->code ?? 'usd');

            $order->update(['payment_intent_id' => $intent->id]);

            return response()->json([
                'clientSecret' => $intent->client_secret,
                'orderId' => $order->id,
            ]);
        } catch (\Exception $e) {
            $order->delete();

            return response()->json(['error' => 'Payment initialization failed.'], 500);
        }
    }

    protected function getCart(Request $request): Cart
    {
        if ($user = $request->user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        return Cart::firstOrCreate(['session_id' => $request->session()->getId()]);
    }

    public function registerFromOrder(Request $request, Order $order)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Invalid or expired registration link.');
        }

        if ($order->user_id) {
            return redirect()->route('login')->with('status', 'This order is already linked to an account.');
        }

        $email = $request->query('email', $order->customer_email);

        if (User::where('email', $email)->exists()) {
            return redirect()->route('login')->with('status', 'An account with this email already exists. Please sign in.');
        }

        return Inertia::render('shop/storefront/postcheckoutregister', [
            'order' => $order->only(['id', 'order_number', 'customer_email', 'customer_name', 'total', 'status']),
            'email' => $email,
        ]);
    }

    public function storeRegisterFromOrder(Request $request, Order $order)
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Invalid or expired registration link.');
        }

        if ($order->user_id) {
            return redirect()->route('login')->with('status', 'This order is already linked to an account.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = $request->query('email', $order->customer_email);

        if (User::where('email', $email)->exists()) {
            return redirect()->route('login')->with('status', 'An account with this email already exists.');
        }

        $user = User::create([
            'username' => Str::before($email, '@').'_'.Str::random(4),
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
        ]);

        $customerRole = Role::where('slug', 'customer')->where('slug', '!=', 'admin')->first();
        if ($customerRole) {
            $user->roles()->attach($customerRole->id);
        }

        $order->update(['user_id' => $user->id]);

        event(new Registered($user));
        Auth::login($user);

        $this->cartMergeService->mergeGuestCartIntoUserCart($user);

        return redirect()->route('dashboard.my.orders.show', $order->id)
            ->with('flash', ['success' => 'Account created! You can now manage your orders from your dashboard.']);
    }

    protected function resolveRedirectRoute(Request $request, ?User $user): string
    {
        $referer = $request->header('referer', '');

        if ($user) {
            if (str_contains($referer, '/dashboard/my/checkout')) {
                return 'dashboard.my.checkout.confirmation';
            }
            if (str_contains($referer, '/dashboard/shop/checkout')) {
                return 'dashboard.shop.checkout.confirmation';
            }
            if (str_contains($referer, '/store/checkout')) {
                return 'store.checkout.confirmation';
            }

            return 'dashboard.my.checkout.confirmation';
        }

        return 'store.checkout.confirmation';
    }
}
