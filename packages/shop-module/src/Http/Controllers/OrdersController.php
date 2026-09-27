<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Notifications\AppNotification;
use Nreljed\Platform\Services\NotificationService;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\OrderNote;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Requests\RefundOrderRequest;
use Nreljed\ShopModule\Requests\StoreOrderNoteRequest;
use Nreljed\ShopModule\Requests\StoreOrderRequest;
use Nreljed\ShopModule\Requests\UpdateOrderStatusRequest;
use Nreljed\ShopModule\Services\InventoryService;
use Nreljed\ShopModule\Services\OrderActionService;
use Nreljed\ShopModule\Services\OrderStatusService;
use Nreljed\ShopModule\Services\WebhookService;

class OrdersController extends Controller
{
    protected NotificationService $notificationService;

    protected OrderActionService $orderActionService;

    protected InventoryService $inventoryService;

    protected OrderStatusService $orderStatusService;

    public function __construct(
        NotificationService $notificationService,
        OrderActionService $orderActionService,
        InventoryService $inventoryService,
        private readonly WebhookService $webhookService,
        OrderStatusService $orderStatusService,
    ) {
        $this->notificationService = $notificationService;
        $this->orderActionService = $orderActionService;
        $this->inventoryService = $inventoryService;
        $this->orderStatusService = $orderStatusService;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with(['items', 'user'])->where('status', '!=', 'trash');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', '%'.$request->search.'%')
                    ->orWhere('customer_name', 'like', '%'.$request->search.'%')
                    ->orWhere('customer_email', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('shop/orders/index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status']),
            'statuses' => OrderStatusService::STATUSES,
        ]);
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['items.product', 'user', 'actions.user', 'refunds.creator', 'notes.user']);

        return Inertia::render('shop/orders/show', [
            'order' => $order,
            'statuses' => OrderStatusService::STATUSES,
            'allowedTransitions' => $this->orderStatusService->getAllowedTransitions($order->status),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Order::class);

        $products = Product::published()
            ->select('id', 'name', 'sku', 'price', 'stock_quantity', 'track_stock', 'thumbnail_url')
            ->orderBy('name')
            ->paginate(50);

        $customers = User::whereIn('id', Order::query()->select('user_id')->whereNotNull('user_id'))
            ->orWhere(function ($q) {
                $q->whereNotNull('email');
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(50)
            ->get();

        return Inertia::render('shop/orders/form', [
            'products' => $products,
            'customers' => $customers,
            'order' => null,
            'statuses' => OrderStatusService::STATUSES,
        ]);
    }

    public function storeAdmin(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);

        $validated = $request->validated();

        $user = $validated['customer_type'] === 'existing'
            ? User::find($validated['user_id'])
            : null;

        $order = DB::transaction(function () use ($validated, $request, $user) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $itemSubtotal = $item['unit_price'] * $item['quantity'];
                $subtotal += $itemSubtotal;

                $lineItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $itemSubtotal,
                    'total' => $itemSubtotal,
                    'image_url' => $product->thumbnail_url,
                ];
            }

            $total = $subtotal;

            $order = Order::create([
                'user_id' => $user?->id,
                'customer_name' => $user?->name ?? $validated['customer_name'],
                'customer_email' => $user?->email ?? $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal,
                'total' => $total,
                'status' => $validated['is_draft'] ?? false ? 'draft' : 'pending',
                'payment_method' => 'manual',
                'payment_status' => 'pending',
                'billing_address' => $validated['billing_address'],
                'shipping_address' => $validated['shipping_address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'processed_by' => $request->user()->id,
                // Staff-created orders skip the checkout pipe, which is where
                // the token is normally issued. Without one here an order
                // entered by hand (phone order, trade account) could never be
                // opened by the customer it belongs to, and would silently
                // become unreadable to anyone if that account went away.
                'confirmation_token' => Order::newConfirmationToken(),
            ]);

            $order->items()->createMany($lineItems);

            if (! ($validated['is_draft'] ?? false)) {
                $this->inventoryService->recordSaleForOrder($order, $request->user()->id);
            }

            return $order;
        });

        $this->orderActionService->created($order, $request->user()->id);
        $this->orderActionService->log($order, 'status_set', null, $order->status, $request->user()->id);

        if ($order->status !== 'draft') {
            $this->webhookService->dispatchOrderPlaced($order);
        }

        $flash = ($order->status === 'draft') ? 'Draft order created.' : 'Order created successfully.';

        return redirect()->route('dashboard.shop.orders.show', $order->id)
            ->with('status', $flash);
    }

    public function edit(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $order->load('items');

        $products = Product::published()
            ->select('id', 'name', 'sku', 'price', 'stock_quantity', 'track_stock', 'thumbnail_url')
            ->orderBy('name')
            ->paginate(50);

        $customers = User::whereIn('id', Order::query()->select('user_id')->whereNotNull('user_id'))
            ->orWhere(function ($q) {
                $q->whereNotNull('email');
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(50)
            ->get();

        return Inertia::render('shop/orders/form', [
            'products' => $products,
            'customers' => $customers,
            'order' => $order,
            'statuses' => OrderStatusService::STATUSES,
        ]);
    }

    public function updateAdmin(StoreOrderRequest $request, Order $order)
    {
        $this->authorize('update', $order);

        $validated = $request->validated();

        $user = $validated['customer_type'] === 'existing'
            ? User::find($validated['user_id'])
            : null;

        $oldItemCount = $order->items()->count();
        $oldTotal = (float) $order->total;

        DB::transaction(function () use ($validated, $order, $request, $user) {
            $subtotal = 0;
            $lineItems = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $itemSubtotal = $item['unit_price'] * $item['quantity'];
                $subtotal += $itemSubtotal;

                $lineItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $item['unit_price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $itemSubtotal,
                    'total' => $itemSubtotal,
                    'image_url' => $product->thumbnail_url,
                ];
            }

            $total = $subtotal;

            $order->update([
                'user_id' => $user?->id,
                'customer_name' => $user?->name ?? $validated['customer_name'],
                'customer_email' => $user?->email ?? $validated['customer_email'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal,
                'total' => $total,
                'billing_address' => $validated['billing_address'],
                'shipping_address' => $validated['shipping_address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'processed_by' => $request->user()->id,
            ]);

            $userId = $request->user()->id;

            if ($order->status !== 'draft') {
                $this->inventoryService->recordRefundForOrder($order, $userId);

                $order->items()->delete();
                $order->items()->createMany($lineItems);

                $order->load('items.product', 'items.variation');

                $this->inventoryService->recordSaleForOrder($order, $userId);
            } else {
                $order->items()->delete();
                $order->items()->createMany($lineItems);
            }
        });

        $changeNotes = sprintf(
            '%d items → %d items, total %s → %s',
            $oldItemCount,
            count($validated['items']),
            number_format($oldTotal, 2),
            number_format((float) $order->fresh()->total, 2)
        );
        $this->orderActionService->edited($order, $request->user()->id, $changeNotes);

        return redirect()->route('dashboard.shop.orders.show', $order->id)
            ->with('status', 'Order updated successfully.');
    }

    public function refund(RefundOrderRequest $request, Order $order)
    {
        $this->authorize('refund', $order);

        $validated = $request->validated();

        if (! in_array($order->status, ['pending', 'on-hold', 'confirmed', 'processing', 'completed'])) {
            return redirect()->back()->withErrors(['refund' => 'Order cannot be refunded in its current status.']);
        }

        $userId = $request->user()->id;

        $totalRefund = (float) ($validated['shipping_refund'] ?? 0);

        foreach ($validated['items'] as $item) {
            $totalRefund += (float) $item['amount'];
        }

        // `RefundOrderRequest` caps each line and the shipping, but the sum
        // across lines can still exceed what is left to refund - two refunds
        // of the same line each pass the per-line check. Re-checked inside the
        // transaction against a fresh read so two concurrent refunds cannot
        // both slip past.
        DB::transaction(function () use ($order, $validated, $userId, $totalRefund) {
            $order->refresh();

            $remaining = round((float) $order->total - (float) $order->total_refunded, 2);

            if ($totalRefund > $remaining + 0.001) {
                throw ValidationException::withMessages([
                    'items' => sprintf(
                        'Refund of %s exceeds the %s still refundable on this order.',
                        number_format($totalRefund, 2),
                        number_format(max($remaining, 0), 2)
                    ),
                ]);
            }

            $this->inventoryService->recordRefundForLines($order, $validated['items'], $userId);

            $order->refunds()->create([
                'items' => $validated['items'],
                'shipping_refund' => $validated['shipping_refund'] ?? 0,
                'total' => $totalRefund,
                'reason' => $validated['reason'] ?? null,
                'created_by' => $userId,
            ]);

            $newTotalRefunded = (float) $order->total_refunded + $totalRefund;
            $orderTotal = (float) $order->total;

            $updateData = [
                'total_refunded' => $newTotalRefunded,
                'payment_status' => $newTotalRefunded >= $orderTotal ? 'refunded' : $order->payment_status,
            ];

            if ($newTotalRefunded >= $orderTotal) {
                $updateData['status'] = 'refunded';
                $updateData['refunded_at'] = now();
            }

            $order->update($updateData);
        });

        $totalRefund = collect($validated['items'])->sum('amount') + (float) ($validated['shipping_refund'] ?? 0);

        if ($order->user) {
            $notification = new AppNotification('order.status_changed', [
                'title' => 'Refund issued for '.$order->order_number,
                'message' => 'A refund of '.number_format($totalRefund, 2).' '.$order->currency.' has been processed.',
                'module' => 'Shop',
                'link' => route('dashboard.shop.orders.show', $order->id, false),
                'orderId' => $order->id,
                'orderNumber' => $order->order_number,
                'customerName' => $order->customer_name,
                'orderTotal' => number_format((float) $order->total, 2).' '.$order->currency,
                'orderStatus' => $order->fresh()->status,
                'orderUrl' => route('dashboard.shop.orders.show', $order->id, false),
            ]);
            $this->notificationService->notifyViaPreferences($order->user, 'order.status_changed', $notification);
        }

        $this->orderActionService->refunded($order, $totalRefund, $validated['reason'] ?? null);

        $this->webhookService->dispatchOrderRefunded($order, $totalRefund);

        return redirect()->back()->with('status', 'Refund processed successfully.');
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order)
    {
        $this->authorize('update', $order);

        $validated = $request->validated();

        try {
            $this->orderStatusService->validateTransition($order, $validated['status']);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['status' => $e->getMessage()]);
        }

        $oldStatus = $order->status;
        $newStatus = $validated['status'];

        $updateData = ['status' => $newStatus];

        if ($newStatus === 'completed' && ! $order->paid_at) {
            $updateData['paid_at'] = now();
            $updateData['payment_status'] = 'paid';
        }

        if ($newStatus === 'completed') {
            $updateData['shipped_at'] = now();
        }

        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled' && $oldStatus !== 'draft') {
            $this->inventoryService->recordRefundForOrder($order, $request->user()->id);
        }

        if ($newStatus === 'pending' && $oldStatus === 'draft') {
            $this->inventoryService->recordSaleForOrder($order, $request->user()->id);
        }

        $order->update($updateData);

        if ($order->user) {
            $notificationData = [
                'title' => 'Order '.$order->order_number.' is now '.$newStatus,
                'message' => 'Your order status changed from '.$oldStatus.' to '.$newStatus.'.',
                'module' => 'Shop',
                'link' => route('dashboard.shop.orders.show', $order->id, false),
                'orderId' => $order->id,
                'orderNumber' => $order->order_number,
                'oldStatus' => $oldStatus,
                'newStatus' => $newStatus,
                'customerName' => $order->customer_name,
                'orderTotal' => number_format((float) $order->total, 2).' '.$order->currency,
                'orderStatus' => ucfirst($newStatus),
                'orderUrl' => route('dashboard.shop.orders.show', $order->id, false),
            ];
            $notification = new AppNotification('order.status_changed', $notificationData);
            $this->notificationService->notifyViaPreferences($order->user, 'order.status_changed', $notification);
        }

        $this->orderActionService->statusChanged($order, $oldStatus, $newStatus);
        $this->webhookService->dispatchOrderStatusChanged($order, $oldStatus);

        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            $this->webhookService->dispatch(
                \Nreljed\ShopModule\Events\WebhookEvents::ORDER_CANCELLED,
                ['order_id' => $order->id, 'order_number' => $order->order_number],
            );
        }

        return redirect()->back()->with('status', 'Order status updated.');
    }

    public function markAsPaid(Order $order)
    {
        $this->authorize('update', $order);

        $order->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->orderActionService->paymentReceived($order);

        return redirect()->back()->with('status', 'Payment marked as received.');
    }

    public function storeNote(StoreOrderNoteRequest $request, Order $order)
    {
        $this->authorize('view', $order);

        $note = $order->notes()->create([
            'user_id' => $request->user()->id,
            'content' => $request->input('content'),
            'is_public' => $request->boolean('is_public', false),
        ]);

        $type = $note->is_public ? 'public_note_added' : 'note_added';
        $this->orderActionService->log($order, $type, null, null, $request->user()->id, 'Note: '.str($request->input('content'))->limit(100));

        return redirect()->back()->with('status', 'Note added.');
    }

    public function destroyNote(Order $order, OrderNote $note)
    {
        $this->authorize('update', $order);

        if ($note->order_id !== $order->id) {
            abort(404);
        }

        $note->delete();

        return redirect()->back()->with('status', 'Note deleted.');
    }

    public function convertFromDraft(Order $order)
    {
        $this->authorize('update', $order);

        if ($order->status !== 'draft') {
            return redirect()->back()->withErrors(['status' => 'Only draft orders can be converted.']);
        }

        DB::transaction(function () use ($order) {
            $this->inventoryService->recordSaleForOrder($order, auth()->id());

            $order->update(['status' => 'pending']);
        });

        $this->orderActionService->statusChanged($order, 'draft', 'pending');

        return redirect()->back()->with('status', 'Draft order converted to pending.');
    }

    public function invoice(Order $order, Request $request)
    {
        $this->authorize('view', $order);

        $order->load('items');

        if ($request->boolean('download') || $request->query('format') === 'pdf') {
            $pdf = Pdf::loadView('shop::email.templates.order-invoice', [
                'order' => $order,
                'siteName' => config('app.name'),
            ]);

            return $pdf->download('invoice-'.$order->order_number.'.pdf');
        }

        return view('shop::email.templates.order-invoice', [
            'order' => $order,
            'siteName' => config('app.name'),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $query = Order::with('items');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['order_number', 'status', 'customer_name', 'customer_email', 'subtotal', 'shipping_total', 'tax_total', 'discount_total', 'total', 'payment_status', 'payment_method', 'items', 'created_at']);

            $query->chunk(200, function ($orders) use ($handle) {
                foreach ($orders as $o) {
                    $items = $o->items->map(fn ($i) => $i->product_name.' x'.$i->quantity.'@'.$i->unit_price)->implode('|');
                    fputcsv($handle, [
                        $o->order_number, $o->status, $o->customer_name, $o->customer_email,
                        $o->subtotal, $o->shipping_total, $o->tax_total, $o->discount_total, $o->total,
                        $o->payment_status, $o->payment_method, $items, $o->created_at,
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="orders-'.date('Y-m-d').'.csv"',
        ]);
    }

    public function searchProducts(Request $request)
    {
        // Every other action here authorises individually; the route group's
        // `shop.manage` gate is the outer boundary, not the only one. These two
        // were the exceptions, and `searchCustomers` returns names and emails.
        $this->authorize('viewAny', Order::class);

        $query = $this->searchTerm($request);

        if ($query === null) {
            return response()->json([]);
        }

        $products = Product::published()
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', $query)
                    ->orWhere('sku', 'like', $query);
            })
            ->select('id', 'name', 'sku', 'price', 'stock_quantity', 'track_stock', 'thumbnail_url')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (float) $p->price,
                'stock' => $p->track_stock ? $p->stock_quantity : null,
                'thumbnail_url' => $p->thumbnail_url,
            ]);

        return response()->json($products);
    }

    public function searchCustomers(Request $request)
    {
        $this->authorize('viewAny', Order::class);

        $query = $this->searchTerm($request);

        if ($query === null) {
            return response()->json([]);
        }

        $customers = User::where(function ($q) use ($query) {
            $q->where('name', 'like', $query)
                ->orWhere('email', 'like', $query);
        })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->limit(20)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]);

        return response()->json($customers);
    }

    /**
     * The term these pickers interpolate into a `LIKE` pattern, or null when
     * there is nothing to search for.
     *
     * The raw query string was used as-is, so a caller could send `%` and match
     * every row - on `searchCustomers` that is the full user list, names and
     * emails, in 20 rows.
     *
     * Wildcards are stripped rather than backslash-escaped on purpose. SQLite
     * treats `\` in a `LIKE` pattern as an ordinary character unless the
     * pattern carries an explicit `ESCAPE` clause, so an escaped term matches
     * nothing at all there while working on MySQL - search would pass in
     * production and return empty against SQLite. Stripping needs no clause and
     * behaves identically on both engines; a shopper searching for a literal
     * `%` still matches on the text around it.
     *
     * A term that is empty to begin with, or that was nothing but wildcards, is
     * null rather than a bare `%`. Falling back to `%` there would leave the
     * enumeration the stripping above exists to close, just behind a different
     * character: these are lookup fields for attaching a specific line or
     * customer, so having nothing to search for means having nothing to show.
     */
    private function searchTerm(Request $request): ?string
    {
        $term = mb_substr(trim((string) $request->get('q', '')), 0, 100);
        $term = str_replace(['\\', '%', '_'], '', $term);

        if (trim($term) === '') {
            return null;
        }

        return '%'.$term.'%';
    }
}
