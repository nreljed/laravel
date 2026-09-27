<?php

namespace Nreljed\ShopModule\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Nreljed\ShopModule\Models\Order;

class RefundOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('refund', $this->route('order'));
    }

    public function rules(): array
    {
        $order = $this->route('order');

        return [
            'items' => 'required|array|min:1',
            // Scoped to the order in the route. `exists:order_items,id` on its
            // own was a global check, so refunding order #5 accepted line ids
            // belonging to order #9 and booked their amounts against #5.
            'items.*.order_item_id' => [
                'required',
                'integer',
                Rule::exists('order_items', 'id')->where('order_id', $order?->id),
            ],
            'items.*.quantity' => 'required|integer|min:1',
            // The amount is what actually moves, so it cannot be arbitrary
            // either; `withValidator()` caps it against the line.
            'items.*.amount' => 'required|numeric|min:0',
            'shipping_refund' => 'nullable|numeric|min:0',
            'reason' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Caps that need the order's own lines to express.
     *
     * `rules()` can prove a line exists and that the shape is sane, but not
     * that a refund is *affordable*: the submitted quantity may exceed what was
     * bought, the amount may exceed the line's value, and nothing stopped a
     * second refund from pushing `total_refunded` past the order total. All
     * three are checked here, where the lines are available.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $order = $this->route('order');

            if (! $order) {
                return;
            }

            $order->loadMissing('items');
            $lines = $order->items->keyBy('id');

            foreach ((array) $this->input('items', []) as $index => $row) {
                $line = $lines->get($row['order_item_id'] ?? null);

                if (! $line) {
                    // Ownership failure; `rules()` already reported it.
                    continue;
                }

                $quantity = (int) ($row['quantity'] ?? 0);
                $amount = (float) ($row['amount'] ?? 0);

                if ($quantity > (int) $line->quantity) {
                    $validator->errors()->add(
                        "items.{$index}.quantity",
                        "Only {$line->quantity} of \"{$line->product_name}\" were on this order.",
                    );
                }

                $max = (float) $line->total * $quantity;

                if ($amount > $max) {
                    $validator->errors()->add(
                        "items.{$index}.amount",
                        "Refund cannot exceed the line value of ".number_format($max, 2).'.',
                    );
                }
            }

            $shipping = (float) ($this->input('shipping_refund') ?? 0);
            $maxShipping = (float) $order->shipping_total;

            if ($shipping > $maxShipping) {
                $validator->errors()->add(
                    'shipping_refund',
                    'Shipping refund cannot exceed the shipping charged ('.number_format($maxShipping, 2).').',
                );
            }

            $refundable = $this->remainingRefundable($order);

            if ($refundable < 0) {
                $validator->errors()->add(
                    'items',
                    'This order has already been over-refunded; resolve that before issuing another refund.',
                );

                return;
            }

            // The per-line caps above each hold on their own, so a request can
            // pass every one of them and still refund more than the order is
            // worth: two lines at 60 on a 100 order, or the same line refunded
            // twice. The sum is what actually moves, so it is checked here as
            // well. The controller repeats this inside the transaction against
            // a fresh read, which is what covers a concurrent second refund.
            $requested = 0.0;

            foreach ((array) $this->input('items', []) as $row) {
                $requested += (float) ($row['amount'] ?? 0);
            }

            $requested += $shipping;

            if ($requested > $refundable + 0.001) {
                $validator->errors()->add(
                    'items',
                    sprintf(
                        'Refund of %s exceeds the %s still refundable on this order.',
                        number_format($requested, 2),
                        number_format($refundable, 2)
                    ),
                );
            }
        });
    }

    /**
     * How much more can be returned before the order is fully refunded.
     */
    private function remainingRefundable(Order $order): float
    {
        return round((float) $order->total - (float) $order->total_refunded, 2);
    }
}
