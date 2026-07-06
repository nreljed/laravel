<?php

namespace App\Modules\Posts\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Posts\Models\PostCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostCategoryController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Post::class);

        $categories = PostCategory::withCount('posts')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Modules/Posts/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:post_categories,id',
            'order' => 'nullable|integer|min:0',
        ]);

        PostCategory::create($validated);

        return redirect()->back()->with('status', 'Category created.');
    }

    public function update(Request $request, PostCategory $postCategory)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:post_categories,id',
            'order' => 'nullable|integer|min:0',
        ]);

        $postCategory->update($validated);

        return redirect()->back()->with('status', 'Category updated.');
    }

    public function destroy(PostCategory $postCategory)
    {
        $this->authorize('create', Post::class);

        if ($postCategory->posts()->exists()) {
            return redirect()->back()->withErrors(['delete' => 'Cannot delete category with posts.']);
        }

        $postCategory->delete();

        return redirect()->back()->with('status', 'Category deleted.');
    }
}
