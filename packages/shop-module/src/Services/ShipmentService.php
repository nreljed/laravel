<?php

namespace Nreljed\ShopModule\Services;

use Nreljed\ShopModule\Events\WebhookEvents;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderItem;
use Nreljed\ShopModule\Models\Shipment;

class ShipmentService
{
    public function __construct(
        protected WebhookService $webhookService,
    ) {}

    /**
     * Create a shipment from the order's (trackable) items. When no explicit
     * per-item quantities are given, all order items are included.
     */
    public function createForOrder(
        Order $order,
        ?array $itemQuantities = null,
        ?array $options = null,
        ?int $createdBy = null,
    ): Shipment {
        $options ??= [];

        $shipment = $order->shipments()->create([
            'carrier' => $options['carrier'] ?? null,
            'tracking_number' => $options['tracking_number'] ?? null,
            'tracking_url' => $options['tracking_url'] ?? null,
            'notes' => $options['notes'] ?? null,
            'created_by' => $createdBy,
        ]);

        $order->loadMissing('items');

        $order->items->each(function (OrderItem $item) use ($shipment, $itemQuantities) {
            $quantity = $itemQuantities[$item->id] ?? $item->quantity ?? 1;

            if ($quantity < 1) {
                return;
            }

            $shipment->items()->create([
                'order_item_id' => $item->id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'quantity' => $quantity,
            ]);
        });

        if ($shipment->items()->count() === 0) {
            $shipment->delete();

            throw new \InvalidArgumentException('Shipment must contain at least one item.');
        }

        $this->webhookService->dispatchShipment(WebhookEvents::SHIPMENT_CREATED, $this->payload($shipment));

        return $shipment->refresh();
    }

    public function markShipped(Shipment $shipment, ?string $carrier = null, ?string $trackingNumber = null): Shipment
    {
        $updates = ['status' => 'shipped', 'shipped_at' => now()];

        if ($carrier) {
            $updates['carrier'] = $carrier;
        }

        if ($trackingNumber) {
            $updates['tracking_number'] = $trackingNumber;
        }

        $shipment->update($updates);

        $this->webhookService->dispatchShipment(WebhookEvents::SHIPMENT_SHIPPED, $this->payload($shipment));

        return $shipment->refresh();
    }

    public function markDelivered(Shipment $shipment): Shipment
    {
        $shipment->update(['status' => 'delivered', 'delivered_at' => now()]);

        $this->webhookService->dispatchShipment(WebhookEvents::SHIPMENT_DELIVERED, $this->payload($shipment));

        return $shipment->refresh();
    }

    public function updateTracking(Shipment $shipment, string $carrier, string $trackingNumber, ?string $trackingUrl = null): Shipment
    {
        $updates = ['carrier' => $carrier, 'tracking_number' => $trackingNumber];

        if ($trackingUrl) {
            $updates['tracking_url'] = $trackingUrl;
        }

        $shipment->update($updates);

        $shipment->refresh();

        if ($shipment->isShipped()) {
            $this->webhookService->dispatchShipment(
                $shipment->isDelivered() ? WebhookEvents::SHIPMENT_DELIVERED : WebhookEvents::SHIPMENT_SHIPPED,
                $this->payload($shipment),
            );
        }

        return $shipment;
    }

    protected function payload(Shipment $shipment): array
    {
        $shipment->loadMissing('order', 'items');

        return [
            'id' => $shipment->id,
            'shipment_number' => $shipment->shipment_number,
            'order_id' => $shipment->order_id,
            'order_number' => $shipment->order?->order_number,
            'carrier' => $shipment->carrier,
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->tracking_url,
            'status' => $shipment->status,
            'shipped_at' => $shipment->shipped_at?->toISOString(),
            'delivered_at' => $shipment->delivered_at?->toISOString(),
            'items' => $shipment->items->map(fn ($item) => [
                'order_item_id' => $item->order_item_id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'quantity' => $item->quantity,
            ])->values(),
        ];
    }
}