<?php

namespace Nreljed\DashboardCore\Blog\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Http\Controllers\Controller;

class BlogPublicController extends Controller
{
    public function index(Request $request)
    {
        $query = Post::with('user', 'category', 'tags')
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $category));
        }

        if ($tag = $request->query('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $tag));
        }

        $posts = $query->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Blog/Public/Index', [
            'posts' => $posts,
            'category' => $category ?? null,
            'tag' => $tag ?? null,
        ]);
    }

    public function show(string $slug)
    {
        $post = Post::with('user', 'category', 'tags')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->firstOrFail();

        return Inertia::render('Blog/Public/Show', [
            'post' => $post,
        ]);
    }
}
