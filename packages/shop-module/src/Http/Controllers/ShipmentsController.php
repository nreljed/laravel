<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Shipment;
use Nreljed\ShopModule\Requests\StoreShipmentRequest;
use Nreljed\ShopModule\Requests\UpdateShipmentRequest;
use Nreljed\ShopModule\Services\ShipmentService;

class ShipmentsController extends Controller
{
    public function __construct(
        protected ShipmentService $shipmentService,
    ) {}

    public function index(Order $order): Response
    {
        $this->authorize('viewAny', Shipment::class);

        $order->load(['items', 'shipments.items']);

        return Inertia::render('shop/shipments/index', [
            'order' => $order->only('id', 'order_number', 'customer_name', 'customer_email', 'status'),
            'orderItems' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'quantity' => $item->quantity,
            ]),
            'shipments' => $order->shipments->map(fn (Shipment $shipment) => [
                'id' => $shipment->id,
                'shipment_number' => $shipment->shipment_number,
                'carrier' => $shipment->carrier,
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
                'status' => $shipment->status,
                'shipped_at' => $shipment->shipped_at?->toIso8601String(),
                'delivered_at' => $shipment->delivered_at?->toIso8601String(),
                'items' => $shipment->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'product_sku' => $item->product_sku,
                    'quantity' => $item->quantity,
                ])->values(),
            ])->values(),
        ]);
    }

    public function store(StoreShipmentRequest $request, Order $order): RedirectResponse
    {
        $this->authorize('create', Shipment::class);

        $validated = $request->validated();

        $shipment = DB::transaction(fn () => $this->shipmentService->createForOrder(
            $order,
            $validated['item_quantities'] ?? null,
            $validated,
            $request->user()->id,
        ));

        return redirect()
            ->route('dashboard.shop.shipments.index', $order)
            ->with('flash', ['success' => 'Shipment '.$shipment->shipment_number.' created.']);
    }

    public function update(UpdateShipmentRequest $request, Shipment $shipment): RedirectResponse
    {
        $this->authorize('update', $shipment);

        $validated = $request->validated();

        $action = $validated['action'] ?? null;

        if ($action === 'ship') {
            $this->shipmentService->markShipped($shipment, $validated['carrier'] ?? null, $validated['tracking_number'] ?? null);
        } elseif ($action === 'deliver') {
            $this->shipmentService->markDelivered($shipment);
        } else {
            $this->shipmentService->updateTracking(
                $shipment,
                $validated['carrier'] ?? '',
                $validated['tracking_number'] ?? '',
                $validated['tracking_url'] ?? null,
            );
        }

        return redirect()->back()->with('flash', ['success' => 'Shipment updated.']);
    }
}