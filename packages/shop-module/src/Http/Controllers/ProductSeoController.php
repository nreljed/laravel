<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nreljed\Platform\Ai\Agents\SeoAgent;
use Nreljed\Platform\Ai\Exceptions\MissingAiProviderException;
use Nreljed\Platform\Http\Controllers\Controller;

class ProductSeoController extends Controller
{
    /**
     * Generate SEO metadata options for a product.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'keyword' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $seo = app(SeoAgent::class)->generateSeo(
                user: $request->user(),
                title: $validated['title'],
                content: $validated['content'] ?? '',
                keyword: $validated['keyword'] ?? null,
            );

            return response()->json(['seo' => $seo]);
        } catch (MissingAiProviderException) {
            return response()->json([
                'message' => 'No AI provider configured. Please add an API key in Account → AI Keys.',
            ], 422);
        }
    }
}
