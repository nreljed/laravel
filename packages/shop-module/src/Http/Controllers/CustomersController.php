<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Models\User;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Resources\CustomerResource;

class CustomersController extends Controller
{
    public function index(ResourceRenderer $renderer)
    {
        $this->authorize('viewAny', Order::class);

        $customers = User::whereExists(function ($q) {
            $q->selectRaw('1')
                ->from('orders')
                ->whereColumn('orders.user_id', 'users.id');
        })
            ->addSelect([
                'orders_count' => Order::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('orders.user_id', 'users.id'),
                'total_spent' => Order::query()
                    ->selectRaw('coalesce(sum(total), 0)')
                    ->whereColumn('orders.user_id', 'users.id'),
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'orders_count' => $user->orders_count,
                'total_spent' => (float) ($user->total_spent ?? 0),
                'created_at' => $user->created_at,
            ])
            ->all();

        return $renderer->renderIndex(CustomerResource::KEY, $customers);
    }

    public function show(User $record)
    {
        $this->authorize('viewAny', Order::class);
        $orders = Order::where('user_id', $record->id)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $stats = [
            'total_orders' => Order::where('user_id', $record->id)->count(),
            'total_spent' => (float) Order::where('user_id', $record->id)
                ->whereIn('status', ['completed', 'processing'])
                ->sum('total'),
            'pending_orders' => Order::where('user_id', $record->id)->where('status', 'pending')->count(),
            'completed_orders' => Order::where('user_id', $record->id)->where('status', 'completed')->count(),
        ];

        return Inertia::render('shop/customers/show', [
            'customer' => [
                'id' => $record->id,
                'name' => $record->name,
                'email' => $record->email,
                'created_at' => $record->created_at,
                'stats' => $stats,
            ],
            'orders' => $orders,
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('viewAny', Order::class);
        $query = User::whereExists(function ($q) {
            $q->selectRaw('1')
                ->from('orders')
                ->whereColumn('orders.user_id', 'users.id');
        })
            ->addSelect([
                'orders_count' => Order::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('orders.user_id', 'users.id'),
                'total_spent' => Order::query()
                    ->selectRaw('coalesce(sum(total), 0)')
                    ->whereColumn('orders.user_id', 'users.id'),
            ]);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['name', 'email', 'orders_count', 'total_spent', 'created_at']);

            $query->chunk(200, function ($customers) use ($handle) {
                foreach ($customers as $c) {
                    fputcsv($handle, [
                        $c->name, $c->email, $c->orders_count,
                        number_format((float) ($c->total_spent ?? 0), 2),
                        $c->created_at,
                    ]);
                }
            });

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="customers-'.date('Y-m-d').'.csv"',
        ]);
    }
}
