<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Support\ResourceRenderer;
use Nreljed\ShopModule\Models\Order;
use Nreljed\ShopModule\Models\Product;
use Nreljed\ShopModule\Models\ProductReview;
use Nreljed\ShopModule\Requests\StoreReviewRequest;
use Nreljed\ShopModule\Resources\ReviewResource;

class ReviewsController extends Controller
{
    public function index(ResourceRenderer $renderer): Response
    {
        $this->authorize('viewAny', ProductReview::class);

        $reviews = ProductReview::query()
            ->with(['product:id,name', 'user:id,name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (ProductReview $review): array => [
                ...$review->toArray(),
                // Flat accessors the kernel table columns read; a PHP descriptor
                // cannot carry a `cell` that dereferences a nested relation.
                'product_name' => $review->product?->name,
                'user_name' => $review->user?->name ?? 'Anonymous',
            ])
            ->all();

        return $renderer->renderIndex(ReviewResource::KEY, $reviews);
    }

    public function store(StoreReviewRequest $request, Product $product)
    {
        $this->authorize('create', ProductReview::class);

        // A `hidden` product is not in the shop, so a review form is not the
        // right place to be writing one. Without this the endpoint was another
        // oracle - "you have already reviewed this" versus a success redirect
        // told any signed-in visitor whether a given product id existed, and
        // which ones had already been reviewed - and it stored a row for a
        // product no shopper can reach.
        abort_unless($product->isVisibleOnStorefront(), 404);

        $validated = $request->validated();

        $existing = ProductReview::where('product_id', $product->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            return redirect()->back()->withErrors(['review' => 'You have already reviewed this product.']);
        }

        $orderId = Order::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->where('status', 'completed')
            ->value('id');

        ProductReview::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_id' => $orderId,
            'rating' => $validated['rating'],
            // Both keys are `nullable`, and `validated()` omits a nullable key
            // that was not sent at all - so a rating-only review reached these
            // lines with neither present and the controller died on an
            // undefined array key (a 500 from the review form, not a message).
            // Both columns are nullable, so absent is a legitimate review.
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'] ?? null,
            'is_approved' => false,
        ]);

        return redirect()->back()->with('status', 'Review submitted for approval.');
    }

    public function approve(ProductReview $record)
    {
        $this->authorize('approve', $record);

        $record->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => request()->user()->id,
        ]);

        return redirect()->back()->with('status', 'Review approved.');
    }

    public function reject(ProductReview $record)
    {
        $this->authorize('approve', $record);

        $record->update([
            'is_approved' => false,
            'approved_at' => null,
            'approved_by' => request()->user()->id,
        ]);

        return redirect()->back()->with('status', 'Review rejected.');
    }

    public function destroy(ProductReview $record)
    {
        $this->authorize('delete', $record);

        $record->delete();

        return redirect()->back()->with('status', 'Review deleted.');
    }
}
