<?php

namespace App\TestPosts\Controllers;

use App\Http\Controllers\Controller;
use App\TestPosts\Models\TestPost;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TestPostPublicController extends Controller
{
    public function index(Request $request)
    {
        $query = TestPost::with('user')
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });

        $testPosts = $query->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('TestPosts/Public/Index', [
            'testPosts' => $testPosts,
        ]);
    }

    public function show(string $slug)
    {
        $testPost = TestPost::with('user')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->firstOrFail();

        return Inertia::render('TestPosts/Public/Show', [
            'testPost' => $testPost,
        ]);
    }
}
