<?php

namespace App\Modules\Posts\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Posts\Models\Post;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $posts = Post::with('user')
            ->latest()
            ->paginate(12);

        return Inertia::render('Posts/Index', [
            'posts' => $posts,
        ]);
    }

    public function create()
    {
        return Inertia::render('Posts/Form', [
            'post' => null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'status' => 'nullable|string|in:draft,published,archived',
            'category' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['published_at'] = ($validated['status'] ?? 'draft') === 'published' ? now() : null;

        Post::create($validated);

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post created successfully.');
    }

    public function edit(Post $post)
    {
        return Inertia::render('Posts/Form', [
            'post' => $post,
        ]);
    }

    public function update(Request $request, Post $post)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'status' => 'nullable|string|in:draft,published,archived',
            'category' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
        ]);

        if (($validated['status'] ?? '') === 'published' && !$post->published_at) {
            $validated['published_at'] = now();
        }

        $post->update($validated);

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post updated successfully.');
    }

    public function destroy(Post $post)
    {
        $post->delete();

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post deleted successfully.');
    }
}
