<?php

namespace App\Blog\Controllers;

use App\Blog\Models\Post;
use App\Blog\Models\PostCategory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostCategoryController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Post::class);

        $categories = PostCategory::withCount('posts')
            ->with('children')
            ->ordered()
            ->get();

        return Inertia::render('Blog/Categories/Index', [
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
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
        ]);

        if (! empty($validated['parent_id'])) {
            $validated['depth'] = PostCategory::find($validated['parent_id'])->depth + 1;
        }

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
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
        ]);

        if (isset($validated['parent_id']) && $validated['parent_id'] == $postCategory->id) {
            return redirect()->back()->withErrors(['parent_id' => 'A category cannot be its own parent.']);
        }

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

    public function reorder(Request $request)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:post_categories,id',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            PostCategory::where('id', $id)->update(['order' => $index]);
        }

        return redirect()->back()->with('status', 'Category order updated.');
    }

    public function bulk(Request $request)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'action' => 'required|string|in:delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:post_categories,id',
        ]);

        $categories = PostCategory::whereIn('id', $validated['ids'])->get();

        foreach ($categories as $category) {
            if ($category->posts()->exists()) {
                return redirect()->back()->withErrors([
                    'bulk' => "Cannot delete \"{$category->name}\" — it has posts assigned.",
                ]);
            }
        }

        PostCategory::whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('status', count($validated['ids']).' categories deleted.');
    }
}
