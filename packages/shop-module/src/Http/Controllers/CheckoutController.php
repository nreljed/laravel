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
use Nreljed\ShopModule\Events\WebhookEvents;
use Nreljed\ShopModule\Models\Cart;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Services\CartMergeService;
use Nreljed\ShopModule\Services\CheckoutService;
use Nreljed\ShopModule\Services\CurrencyService;
use Nreljed\ShopModule\Services\PaymentManager;
use Nreljed\ShopModule\Services\QuoteService;
use Nreljed\ShopModule\Services\ShippingService;
use Nreljed\ShopModule\Services\ShopSettingsService;
use Nreljed\ShopModule\Services\WebhookService;

class CheckoutController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected MailService $mailService,
        protected ShippingService $shippingService,
        protected PaymentManager $paymentManager,
        protected CurrencyService $currencyService,
        protected CartMergeService $cartMergeService,
        protected CheckoutService $checkoutService,
        protected ShopSettingsService $shopSettings,
        protected QuoteService $quoteService,
        protected ?WebhookService $webhookService = null,
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

        // Single source of truth for checkout math — the same quote the
        // persist path stores on the order.
        $selectedShipping = $shippingMethods->first();
        $quote = $this->quoteService->quote(
            $cart,
            $selectedShipping,
            $billingAddress,
            $shippingAddress,
            $user?->id,
        );

        $cart->discount = $quote['discount_total'];
        $cart->currency = $quote['currency'];

        // Display-only conversion: cart/shipping/tax figures are served in the
        // storefront's selected currency, but the order persists in the
        // default (cart) currency.
        $default = $this->currencyService->defaultCode();
        $display = $this->currencyService->storefrontCode();

        if ($display !== $default) {
            foreach ($cart->items as $item) {
                $item->unit_price = $this->currencyService->convert((float) $item->unit_price, $default, $display);
            }
            $cart->discount = $this->currencyService->convert((float) $quote['discount_total'], $default, $display);
            $cart->currency = $display;

            foreach ($shippingMethods as $method) {
                $method->price = $this->currencyService->convert((float) $method->price, $default, $display);
                if ($method->free_above !== null) {
                    $method->free_above = $this->currencyService->convert((float) $method->free_above, $default, $display);
                }
            }

            $tax = $quote['tax'];
            if (isset($tax['tax_total'])) {
                $tax['tax_total'] = $this->currencyService->convert((float) $tax['tax_total'], $default, $display);
            }
            foreach ($tax['breakdown'] ?? [] as $i => $row) {
                if (isset($row['amount'])) {
                    $tax['breakdown'][$i]['amount'] = $this->currencyService->convert((float) $row['amount'], $default, $display);
                }
            }
        } else {
            $tax = $quote['tax'];
        }

        return Inertia::render('shop/orders/checkout', [
            'cart' => $cart,
            'shippingMethods' => $shippingMethods,
            'userBillingAddress' => $billingAddress,
            'userShippingAddress' => $shippingAddress,
            'stripeKey' => $this->paymentManager->has('stripe') ? ($this->paymentManager->driver('stripe')->getPublishableKey() ?? '') : '',
            'currency' => $display,
            'estimatedTax' => $tax,
            'freeShippingThreshold' => $shippingMethods->min('free_above'),
            'freeShippingApplied' => $quote['free_shipping_applied'],
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

                $this->webhookService?->dispatch(
                    WebhookEvents::CUSTOMER_CREATED,
                    [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                );
            }
        }

        $provisionalOrder = null;

        if (($validated['payment_method'] ?? '') === 'stripe' && ! empty($validated['stripe_payment_intent_id'] ?? '')) {
            $provisionalOrder = Order::where('payment_intent_id', $validated['stripe_payment_intent_id'])
                ->where('payment_method', 'stripe')
                ->where('status', config('shop.payments.provisional_draft_status', 'draft'))
                ->first();
        }

        $order = $this->checkoutService->processCheckout($cart, $validated, $user, $provisionalOrder);

        $this->webhookService?->dispatchOrderPlaced($order);

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
            'orderTotal' => number_format((float) $order->total, 2).' '.$order->currency,
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
            $quote = $this->quoteService->quote(
                $cart,
                $this->shippingService->getAvailableMethods($user?->billing_address ?? $cart->billing_address)->first(),
                $user?->billing_address ?? $cart->billing_address,
                $user?->shipping_address ?? $cart->shipping_address,
                $user?->id,
            );

            return Order::create([
                'user_id' => $user?->id,
                'customer_email' => $user?->email ?? 'pending@checkout.com',
                'customer_name' => $user?->name ?? 'Pending',
                'subtotal' => $quote['subtotal'],
                'discount_total' => $quote['discount_total'],
                'shipping_total' => $quote['shipping_total'],
                'tax_total' => $quote['tax']['tax_total'],
                'total' => $quote['total'],
                'currency' => $quote['currency'],
                'exchange_rate' => $quote['exchange_rate'],
                'status' => config('shop.payments.provisional_draft_status', 'draft'),
                'payment_method' => 'stripe',
                'payment_status' => 'pending',
                'applied_coupons' => $quote['coupons']->map(fn ($c) => [
                    'coupon_id' => $c->id,
                    'code' => $c->code,
                    'type' => $c->type,
                ])->toArray(),
            ]);
        });

        try {
            $result = $this->paymentManager->driver('stripe')->createPaymentIntent($order, $order->currency);

            $order->update(['payment_intent_id' => $result->id]);

            return response()->json([
                'clientSecret' => $result->clientSecret,
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
            return Cart::firstOrCreate(['user_id' => $user->id], ['currency' => $this->currencyService->defaultCode()]);
        }

        return Cart::firstOrCreate(['session_id' => $request->session()->getId()], ['currency' => $this->currencyService->defaultCode()]);
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
