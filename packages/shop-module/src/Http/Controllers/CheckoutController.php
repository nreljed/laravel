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
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Payments\PaymentVerification;
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
        $this->authorizeOrderView($request, $order);

        $order->load('items');

        return Inertia::render('shop/orders/confirmation', [
            'order' => $order,
        ]);
    }

    /**
     * Decide whether this visitor may see this order.
     *
     * The page carries the shopper's name, email, phone, billing and shipping
     * addresses and what they bought, and the guard used to be
     * `if ($order->user_id && ...)`, which does nothing at all for a guest
     * order. Combined with sequential ids that made every guest order
     * world-readable to anyone who incremented a number - and it applied to
     * member orders too, because the `user_id` foreign key is `nullOnDelete`,
     * so deleting an account quietly turned a private order into a public one.
     *
     * Three things now grant access, in the order a caller is most likely to
     * have them:
     *
     *  - being signed in as the shopper who placed it;
     *  - holding the confirmation token issued when the order was created,
     *    which is what the shopper's own browser and their inbox carry;
     *  - a valid URL signature, for the flows that build links server-side.
     *
     * Shop staff are exempt: the same action backs the `/dashboard/shop`
     * confirmation route, which already sits behind `permission:shop.manage`,
     * and staff have to be able to open a customer's order. Without that
     * exemption the owner check below would refuse them every member order.
     *
     * Note the token is deliberately not tied to `user_id`: an order outlives
     * the account that placed it, and the owner still needs to reach it.
     */
    protected function authorizeOrderView(Request $request, Order $order): void
    {
        if ($order->belongsToUser($request->user())) {
            return;
        }

        if ($request->user()?->can('shop.manage')) {
            return;
        }

        $token = $request->query('token');

        if ($order->hasValidConfirmationToken(is_string($token) ? $token : null)) {
            return;
        }

        if ($request->hasValidSignature()) {
            return;
        }

        abort(403);
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
                    \Nreljed\ShopModule\Events\WebhookEvents::CUSTOMER_CREATED,
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
            $provisionalOrder = $this->findOwnProvisionalOrder($cart, $user, $validated['stripe_payment_intent_id']);
        }

        $order = $this->checkoutService->processCheckout($cart, $validated, $user, $provisionalOrder);

        $verification = $this->verifyPayment($order, $validated);

        if ($verification->isFailed()) {
            $order->update(['payment_status' => 'failed']);

            throw ValidationException::withMessages([
                'payment_method' => $verification->reason ?? 'We could not confirm your payment. Please try again.',
            ]);
        }

        if ($verification->isPaid()) {
            $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
        }

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
                // The order's confirmation token rides along as well. The
                // signature stops working after a week, and a shopper reading
                // this in their inbox later should not find the link dead.
                ['order' => $order->id, 'email' => $order->customer_email, 'token' => $order->confirmation_token],
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

        // Report what actually happened to the payment. Telling a shopper
        // "placed successfully" while the order sits unpaid (manual transfer,
        // an async method still clearing) is how the previous bug stayed
        // invisible from the storefront side.
        $flash = $order->payment_status === 'paid'
            ? ['success' => 'Order placed successfully!']
            : ['success' => 'Order received. We are confirming your payment — you will be emailed as soon as it clears.'];

        // A guest has no session to identify them on the confirmation page, so
        // the link has to carry the order's token or the redirect they are
        // being sent to would refuse them. Members are identified by their
        // session, so their URL stays clean.
        return redirect()->route($redirectRoute, $this->confirmationRedirectParameters($order))
            ->with('flash', $flash);
    }

    /**
     * Route parameters for the post-checkout redirect.
     *
     * A guest has no session to identify them on the confirmation page, so
     * their link has to carry the order's token - without it the very first
     * thing that happens after a successful guest checkout is a 403 on their
     * own order. Members are identified by their session, so their URL stays
     * clean and does not leak the token into their address bar, history, or a
     * shared screenshot of the page.
     *
     * @return array<string, mixed>
     */
    protected function confirmationRedirectParameters(Order $order): array
    {
        $parameters = ['order' => $order->id];

        if (! $order->user_id) {
            $parameters['token'] = $order->confirmation_token;
        }

        return $parameters;
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
                'cart_id' => $cart->id,
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

    /**
     * Find the draft order this checkout is allowed to claim.
     *
     * The lookup used to be a bare `where('payment_intent_id', ...)` with no
     * ownership check, and `CreateOrder` then wrote the caller's cart,
     * addresses and email onto whatever row it matched - so anyone who learned
     * another shopper's intent could overwrite that stranger's draft order.
     *
     * The anchor is the cart: `getCart()` resolves it from the session or the
     * authenticated user, so a draft created through `createPaymentIntent()`
     * for this cart is provably this shopper's. A reference belonging to
     * someone else simply matches nothing, and checkout proceeds on a fresh
     * order - the gateway check in `verifyPayment()` is what stops that fresh
     * order from being marked paid.
     */
    protected function findOwnProvisionalOrder(Cart $cart, ?User $user, string $paymentIntentId): ?Order
    {
        $query = Order::where('payment_intent_id', $paymentIntentId)
            ->where('payment_method', 'stripe')
            ->where('status', config('shop.payments.provisional_draft_status', 'draft'));

        if (($draft = $query->where('cart_id', $cart->id)->first()) !== null) {
            return $draft;
        }

        // Drafts created before `orders.cart_id` existed have no cart link, so
        // fall back to the user for members. Guests get no fallback: there is
        // nothing left to prove ownership with.
        if (! $user) {
            return null;
        }

        return $query->whereNull('cart_id')->where('user_id', $user->id)->first();
    }

    /**
     * Ask the gateway what actually happened, and translate the answer into
     * the order's payment state.
     *
     * Runs after the checkout transaction has committed: a remote gateway call
     * must not be made while holding a database transaction open. Manual
     * payments are not confirmed here at all - they are reconciled by staff -
     * so they are left pending.
     */
    protected function verifyPayment(Order $order, array $validated): PaymentVerification
    {
        $method = (string) ($validated['payment_method'] ?? '');

        if ($method === '' || $method === 'manual') {
            return PaymentVerification::pending();
        }

        $reference = (string) ($validated['stripe_payment_intent_id'] ?? $order->payment_intent_id ?? '');

        if ($reference === '') {
            return PaymentVerification::failed('We could not confirm your payment. Please try again.');
        }

        try {
            $driver = $this->paymentManager->driver($method);
        } catch (\Throwable $e) {
            return PaymentVerification::failed('That payment method is not available.');
        }

        try {
            return $driver->verifyPayment($reference, $order);
        } catch (\Throwable $e) {
            // Never treat an unreachable gateway as a successful payment.
            return PaymentVerification::pending('We are still confirming your payment.');
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
        $this->authorizeRegistrationLink($request, $order);

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
            // Built here rather than in the page. The visitor arrived on a
            // signed link, and both the form's action and the "back to order"
            // link used to be assembled from the order id alone - which threw
            // the signature away, so the form submitted a URL that could only
            // ever come back 401.
            'registerUrl' => $this->registerFormUrl($request, $order),
            'confirmationUrl' => $this->orderConfirmationUrl($order),
        ]);
    }

    /**
     * Guard the "create an account from your order" pages.
     *
     * Either proof works: the temporary signature the emailed link carries, or
     * the order's confirmation token. The signature expires, the token does
     * not, and a shopper who dismisses the prompt for a week should still be
     * able to follow it - the token is exactly as unguessable as the
     * signature, it simply does not have a timer on it.
     */
    protected function authorizeRegistrationLink(Request $request, Order $order): void
    {
        $token = $request->query('token');

        if ($request->hasValidSignature()) {
            return;
        }

        if ($order->hasValidConfirmationToken(is_string($token) ? $token : null)) {
            return;
        }

        abort(401, 'Invalid or expired registration link.');
    }

    /**
     * The guest-registration form action, preserving whatever proof the
     * visitor arrived with.
     */
    protected function registerFormUrl(Request $request, Order $order): string
    {
        return route('store.checkout.store-register', array_merge(
            ['order' => $order->id],
            $this->orderAccessParams($request, $order),
        ));
    }

    /**
     * A link that proves the holder may see this order without an account.
     */
    protected function orderConfirmationUrl(Order $order): string
    {
        return route('store.checkout.confirmation', [
            'order' => $order->id,
            'token' => $order->confirmation_token,
        ]);
    }

    /**
     * The proof a visitor is carrying, forwarded so it survives navigation.
     *
     * The signature is passed through for links that are already signed, and
     * the token for everything else - the token is issued at checkout and
     * never expires, so a registration prompt the shopper opens a fortnight
     * later is not a dead end just because a temporary signature lapsed.
     *
     * @return array<string, string>
     */
    protected function orderAccessParams(Request $request, Order $order): array
    {
        $params = ['token' => (string) $order->confirmation_token];

        foreach (['signature', 'expires', 'email'] as $key) {
            $value = $request->query($key);

            if (is_string($value) && $value !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    public function storeRegisterFromOrder(Request $request, Order $order)
    {
        $this->authorizeRegistrationLink($request, $order);

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
