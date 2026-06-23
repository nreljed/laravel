<?php

namespace App\Modules\ECommerce\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomersController extends Controller
{
    public function index(Request $request)
    {
        $query = User::whereHas('orders');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            });
        }

        $customers = $query->withCount('orders')
            ->withSum('orders as total_spent', 'total')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'orders_count' => $user->orders_count,
                'total_spent' => (float) ($user->total_spent ?? 0),
                'created_at' => $user->created_at,
            ]);

        return Inertia::render('ECommerce/Orders/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search']),
        ]);
    }
}
