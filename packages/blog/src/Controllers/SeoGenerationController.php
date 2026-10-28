<?php

namespace Nreljed\Blog\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Nreljed\Blog\Jobs\GenerateSeoForPost;
use Nreljed\Blog\Models\Post;
use Nreljed\Blog\Requests\BulkSeoRequest;
use Nreljed\Blog\Requests\GenerateSeoRequest;
use Nreljed\Blog\Services\SeoSuggestionResolver;
use Nreljed\Platform\Ai\Agents\SeoAgent;
use Nreljed\Platform\Ai\Exceptions\MissingAiProviderException;
use Nreljed\Platform\Http\Controllers\Controller;

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

            $seo = app(SeoSuggestionResolver::class)->resolve($seo);

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

        $query = Post::query()
            ->when(! $user->hasPermission('posts.manage'), fn ($q) => $q->where('user_id', $user->id));

        if ($request->boolean('all')) {
            $query->where(function ($q) {
                $q->whereNull('meta_title')->orWhere('meta_title', '');
            });
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
