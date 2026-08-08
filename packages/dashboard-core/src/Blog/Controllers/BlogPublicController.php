<?php

namespace Nreljed\DashboardCore\Blog\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Blog\Models\PostCategory;
use Nreljed\DashboardCore\Blog\Models\PostTag;
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

        $categoryModel = $category
            ? PostCategory::query()->where('slug', $category)->first()
            : null;
        $tagModel = $tag
            ? PostTag::query()->where('slug', $tag)->first()
            : null;

        return Inertia::render('Blog/Public/Index', [
            'posts' => $posts,
            'category' => $categoryModel ? [
                'name' => $categoryModel->name,
                'slug' => $categoryModel->slug,
                'meta_title' => $categoryModel->meta_title,
                'meta_description' => $categoryModel->meta_description,
            ] : null,
            'tag' => $tagModel ? [
                'name' => $tagModel->name,
                'slug' => $tagModel->slug,
            ] : null,
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
