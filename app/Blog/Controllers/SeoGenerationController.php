<?php

namespace App\Blog\Controllers;

use App\Ai\Agents\SeoAgent;
use App\Ai\Exceptions\MissingAiProviderException;
use App\Blog\Models\Post;
use App\Blog\Requests\BulkSeoRequest;
use App\Blog\Requests\GenerateSeoRequest;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateSeoForPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;

class SeoGenerationController extends Controller
{
    public function generate(GenerateSeoRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $seo = app(SeoAgent::class)->generateSeo(
                user: $user,
                title: $request->validated('title'),
                content: $request->validated('content', ''),
                keyword: $request->validated('keyword'),
            );

            return response()->json(['seo' => $seo]);
        } catch (MissingAiProviderException) {
            return response()->json([
                'message' => 'No AI provider configured. Please add an API key in Account → AI Keys.',
            ], 422);
        }
    }

    public function bulk(BulkSeoRequest $request): JsonResponse
    {
        $user = $request->user();

        $query = Post::query();

        if ($request->boolean('all')) {
            $query->whereNull('meta_title')->orWhere('meta_title', '');
        } else {
            $query->whereIn('id', $request->validated('post_ids', []));
        }

        $posts = $query->get(['id', 'title', 'content']);

        if ($posts->isEmpty()) {
            return response()->json(['message' => 'No posts found to process.'], 404);
        }

        $jobs = $posts->map(fn (Post $post) => new GenerateSeoForPost($post, $user->id))->all();

        Bus::batch($jobs)->dispatch();

        return response()->json([
            'message' => "Generating SEO for {$posts->count()} post(s).",
            'count' => $posts->count(),
        ]);
    }
}
