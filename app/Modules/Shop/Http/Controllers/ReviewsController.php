<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductReview;
use App\Modules\Shop\Requests\StoreReviewRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReviewsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', ProductReview::class);

        $query = ProductReview::with(['product:id,name', 'user:id,name']);

        if ($request->filled('status')) {
            if ($request->status === 'pending') {
                $query->where('is_approved', false);
            } elseif ($request->status === 'approved') {
                $query->where('is_approved', true);
            }
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', '%'.$request->search.'%'))
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->search.'%'))
                    ->orWhere('title', 'like', '%'.$request->search.'%');
            });
        }

        $reviews = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('Modules/Shop/Reviews/Index', [
            'reviews' => $reviews,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function store(StoreReviewRequest $request, Product $product)
    {
        $this->authorize('create', ProductReview::class);

        $validated = $request->validated();

        $existing = ProductReview::where('product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return redirect()->back()->withErrors(['review' => 'You have already reviewed this product.']);
        }

        $orderId = $request->user()->orders()
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->where('status', 'completed')
            ->value('id');

        ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_id' => $orderId,
            'rating' => $validated['rating'],
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_approved' => false,
        ]);

        return redirect()->back()->with('status', 'Review submitted for approval.');
    }

    public function approve(ProductReview $review)
    {
        $this->authorize('approve', $review);

        $review->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => request()->user()->id,
        ]);

        return redirect()->back()->with('status', 'Review approved.');
    }

    public function reject(ProductReview $review)
    {
        $this->authorize('approve', $review);

        $review->update([
            'is_approved' => false,
            'approved_at' => null,
            'approved_by' => request()->user()->id,
        ]);

        return redirect()->back()->with('status', 'Review rejected.');
    }

    public function destroy(ProductReview $review)
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()->back()->with('status', 'Review deleted.');
    }
}
