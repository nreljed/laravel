<?php

namespace Nreljed\DashboardCore\Blog\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\DashboardCore\Blog\Models\PostCategory;
use Nreljed\DashboardCore\Http\Controllers\Controller;

class PostCategoryController extends Controller
{
    public function index()
    {
        $this->authorize('manage-categories');

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
        $this->authorize('manage-categories');

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

        $category = PostCategory::create($validated);

        if ($request->wantsJson()) {
            return response()->json($category);
        }

        return redirect()->back()->with('status', 'Category created.');
    }

    public function update(Request $request, PostCategory $postCategory)
    {
        $this->authorize('manage-categories');

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

        if (! empty($validated['parent_id'])
            && in_array((int) $validated['parent_id'], $postCategory->getDescendantIds(), true)) {
            return redirect()->back()->withErrors(['parent_id' => 'A category cannot be a descendant of itself.']);
        }

        $postCategory->update($validated);

        if ($request->wantsJson()) {
            return response()->json($postCategory);
        }

        return redirect()->back()->with('status', 'Category updated.');
    }

    public function destroy(Request $request, PostCategory $postCategory)
    {
        $this->authorize('manage-categories');

        if ($postCategory->posts()->exists()) {
            return redirect()->back()->withErrors(['delete' => 'Cannot delete category with posts.']);
        }

        $postCategory->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back()->with('status', 'Category deleted.');
    }

    public function reorder(Request $request)
    {
        $this->authorize('manage-categories');

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
        $this->authorize('manage-categories');

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
