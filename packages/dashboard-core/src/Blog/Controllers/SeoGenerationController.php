<?php

namespace Nreljed\DashboardCore\Blog\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Nreljed\DashboardCore\Ai\Agents\SeoAgent;
use Nreljed\DashboardCore\Ai\Exceptions\MissingAiProviderException;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Blog\Requests\BulkSeoRequest;
use Nreljed\DashboardCore\Blog\Requests\GenerateSeoRequest;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Jobs\GenerateSeoForPost;

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
                'message' => 'No AI provider configured. Please add an API key in Account â†’ AI Keys.',
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
