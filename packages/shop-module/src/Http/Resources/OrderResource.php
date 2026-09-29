<?php

namespace Nreljed\ShopModule\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nreljed\ShopModule\Services\CurrencyService;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'shipping_total' => (float) ($this->shipping_total ?? 0),
            // The snapshot, not the relation: it is what the order was actually
            // sent with, and it survives the method being renamed or deleted.
            // The live relation name rides alongside purely so an operator can
            // tell "the method was deleted" from "it was called something else".
            'shipping_method_name' => $this->shipping_method_name,
            'shipping_method' => $this->whenLoaded('shippingMethod', fn () => $this->shippingMethod?->name),
            'tax_total' => (float) ($this->tax_total ?? 0),
            'discount_total' => (float) ($this->discount_total ?? 0),
            'total' => (float) $this->total,
            'currency' => $this->currency ?? app(CurrencyService::class)->defaultCode(),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'billing_address' => $this->billing_address,
            'shipping_address' => $this->shipping_address,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
