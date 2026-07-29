<?php

namespace App\Blog\Controllers;

use App\Blog\Models\Post;
use App\Blog\Models\PostTag;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostTagController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Post::class);

        $tags = PostTag::withCount('posts')->orderBy('name')->get();

        if (request()->wantsJson()) {
            return response()->json($tags);
        }

        return Inertia::render('Blog/Tags/Index', [
            'tags' => $tags,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:post_tags,name',
            'description' => 'nullable|string|max:500',
        ]);

        $tag = PostTag::create($validated);

        if ($request->wantsJson()) {
            return response()->json($tag);
        }

        return redirect()->route('dashboard.posts.tags.index')->with('flash', ['status' => 'Tag created.']);
    }

    public function update(Request $request, PostTag $postTag)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:post_tags,name,'.$postTag->id,
            'description' => 'nullable|string|max:500',
        ]);

        $postTag->update($validated);

        if ($request->wantsJson()) {
            return response()->json($postTag);
        }

        return redirect()->route('dashboard.posts.tags.index')->with('flash', ['status' => 'Tag updated.']);
    }

    public function destroy(PostTag $postTag)
    {
        $this->authorize('create', Post::class);

        $postTag->delete();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('dashboard.posts.tags.index')->with('flash', ['status' => 'Tag deleted.']);
    }

    public function reorder(Request $request)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:post_tags,id',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            PostTag::where('id', $id)->update(['order' => $index]);
        }

        return redirect()->back()->with('status', 'Tag order updated.');
    }

    public function bulk(Request $request)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'action' => 'required|string|in:delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:post_tags,id',
        ]);

        PostTag::whereIn('id', $validated['ids'])->delete();

        return redirect()->back()->with('status', count($validated['ids']).' tags deleted.');
    }
}
