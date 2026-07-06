<?php

namespace App\Modules\Posts\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Posts\Models\Post;
use App\Modules\Posts\Models\PostTag;
use Illuminate\Http\Request;

class PostTagController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Post::class);

        $tags = PostTag::withCount('posts')->orderBy('name')->get();

        if (request()->wantsJson()) {
            return response()->json($tags);
        }

        return Inertia::render('Modules/Posts/Tags/Index', [
            'tags' => $tags,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:post_tags,name',
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
}
