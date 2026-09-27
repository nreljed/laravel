<?php

namespace Nreljed\ShopModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nreljed\Platform\Ai\Agents\SeoAgent;
use Nreljed\Platform\Ai\Exceptions\MissingAiProviderException;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\ShopModule\Models\Product;

class ProductSeoController extends Controller
{
    /**
     * Generate SEO metadata options for a product.
     */
    public function generate(Request $request): JsonResponse
    {
        // Writes the product's SEO fields through the agent, and runs on the
        // caller's own AI key, so it authorises as a product update rather than
        // riding the route group's `shop.manage` alone.
        //
        // An instance rather than `Product::class`: `ProductPolicy::update()`
        // takes the product, and passing the class name makes the Gate invoke
        // the policy with one argument - an ArgumentCountError, a 500 rather
        // than a 403. The policy only reads the permission, so an unsaved
        // instance is enough.
        $this->authorize('update', new Product);

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
