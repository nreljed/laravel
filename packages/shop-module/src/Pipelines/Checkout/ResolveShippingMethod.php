<?php

namespace Nreljed\ShopModule\Pipelines\Checkout;

use Closure;
use Illuminate\Validation\ValidationException;
use Nreljed\ShopModule\Pipelines\CheckoutContext;
use Nreljed\ShopModule\Services\ShippingService;

class ResolveShippingMethod
{
    public function __construct(protected ShippingService $shipping) {}

    /**
     * Settle on the shipping method, against the set this address was offered.
     *
     * The checkout page builds its list through
     * `ShippingService::getAvailableMethods()`, which returns only active
     * methods and, where the country maps to a zone, only that zone's methods.
     * Submitting used to do a bare `ShippingMethod::findOrFail()` on the posted
     * id instead, so the two halves disagreed and the client was the one holding
     * the stronger claim: anything that reached the table by id could be
     * submitted, whether an admin had switched it off or it belonged to a zone
     * that does not serve the address. `QuoteService` then priced the order
     * from that method, so a shopper could pick whichever disabled or
     * out-of-zone method happened to suit them.
     *
     * The offer is the authority, so the method is resolved out of the very
     * same collection the page rendered. That also preserves the zone pivot's
     * `override_price`, which a fresh `ShippingMethod` lookup would drop and
     * quietly charge a different amount from the one displayed.
     */
    public function handle(CheckoutContext $context, Closure $next)
    {
        $available = $this->shipping->getAvailableMethods($context->validated['billing_address'] ?? null);

        $method = $available->firstWhere('id', $context->validated['shipping_method_id'] ?? null);

        if ($method === null) {
            throw ValidationException::withMessages([
                'shipping_method_id' => 'Please choose a shipping method available for your address.',
            ]);
        }

        $context->shippingMethod = $method;

        return $next($context);
    }
}
