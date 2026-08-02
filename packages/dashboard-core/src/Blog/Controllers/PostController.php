<?php

namespace Nreljed\DashboardCore\Blog\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nreljed\DashboardCore\Blog\Models\ContentBlock;
use Nreljed\DashboardCore\Blog\Models\Post;
use Nreljed\DashboardCore\Blog\Models\PostCategory;
use Nreljed\DashboardCore\Blog\Models\PostTag;
use Nreljed\DashboardCore\Blog\Requests\BulkActionRequest;
use Nreljed\DashboardCore\Http\Controllers\Controller;
use Nreljed\DashboardCore\Services\HtmlSanitizer;
use Nreljed\DashboardCore\Services\MediaService;

class PostController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Post::class);

        $posts = Post::with('user', 'category', 'tags')
            ->latest()
            ->get(['id', 'title', 'slug', 'excerpt', 'status', 'featured_image', 'published_at', 'category_id', 'user_id', 'created_at', 'meta_title']);

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
            'categories' => PostCategory::orderBy('name')->get(['id', 'name']),
        ]);

    }

    public function create()
    {
        $this->authorize('create', Post::class);

        return Inertia::render('Blog/Form', [
            'post' => null,
            'categories' => PostCategory::flatOptions(),
            'tags' => PostTag::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,published,archived,future',
            'publish_date' => 'nullable|date|required_if:status,future',
            'category_id' => 'nullable|exists:post_categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:post_tags,id',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'og_image' => 'nullable|string|max:255',
            'focus_keyword' => 'nullable|string|max:100',
            'seo_metadata' => 'nullable|array',
            'seo_metadata.seo_score' => 'nullable|integer|min:0|max:100',
            'seo_metadata.primary_keyword' => 'nullable|string|max:100',
            'seo_metadata.secondary_keywords' => 'nullable|array',
            'seo_metadata.title_options' => 'nullable|array',
            'seo_metadata.meta_description_options' => 'nullable|array',
            'seo_metadata.recommendations' => 'nullable|array',
            'seo_metadata.recommendations.*.field' => 'required|string',
            'seo_metadata.recommendations.*.severity' => 'required|string|in:critical,warning,info',
            'seo_metadata.recommendations.*.message' => 'required|string',
            'content_blocks' => 'nullable|array',
            'content_blocks.*.type' => 'required|string|in:rich_text,image,gallery,embed,code,separator,heading',
            'content_blocks.*.content' => 'nullable|string',
            'content_blocks.*.media_url' => 'nullable|string|max:255',
            'content_blocks.*.media_alt' => 'nullable|string|max:255',
            'content_blocks.*.settings' => 'nullable|array',
            'content_blocks.*.order' => 'nullable|integer',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated = $this->applyPostScheduleStatus($validated);
        $validated = $this->sanitizePostContent($validated);

        $tagIds = $validated['tag_ids'] ?? [];
        $contentBlocks = $validated['content_blocks'] ?? [];
        $seoMetadata = $validated['seo_metadata'] ?? null;
        unset($validated['tag_ids'], $validated['content_blocks'], $validated['seo_metadata']);

        if ($seoMetadata) {
            $existingMetadata = $validated['metadata'] ?? [];
            $validated['metadata'] = array_merge($existingMetadata, [
                'seo' => $seoMetadata,
                'seo_generated_at' => now()->toISOString(),
            ]);
        }

        $post = Post::create($validated);

        if (! empty($tagIds)) {
            $post->tags()->attach($tagIds);
        }

        if (! empty($contentBlocks)) {
            foreach ($contentBlocks as $block) {
                $post->contentBlocks()->create($this->sanitizeBlock($block));
            }
        }

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post created successfully.');
    }

    public function edit(Post $post)
    {
        $this->authorize('update', $post);

        $post->load('contentBlocks', 'tags', 'category');

        return Inertia::render('Blog/Form', [
            'post' => $post,
            'categories' => PostCategory::flatOptions($post->category_id),
            'tags' => PostTag::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:draft,published,archived,future',
            'publish_date' => 'nullable|date|required_if:status,future',
            'category_id' => 'nullable|exists:post_categories,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:post_tags,id',
            'meta_title' => 'nullable|string|max:70',
            'meta_description' => 'nullable|string|max:160',
            'og_image' => 'nullable|string|max:255',
            'focus_keyword' => 'nullable|string|max:100',
            'seo_metadata' => 'nullable|array',
            'seo_metadata.seo_score' => 'nullable|integer|min:0|max:100',
            'seo_metadata.primary_keyword' => 'nullable|string|max:100',
            'seo_metadata.secondary_keywords' => 'nullable|array',
            'seo_metadata.title_options' => 'nullable|array',
            'seo_metadata.meta_description_options' => 'nullable|array',
            'seo_metadata.recommendations' => 'nullable|array',
            'seo_metadata.recommendations.*.field' => 'required|string',
            'seo_metadata.recommendations.*.severity' => 'required|string|in:critical,warning,info',
            'seo_metadata.recommendations.*.message' => 'required|string',
            'content_blocks' => 'nullable|array',
            'content_blocks.*.id' => 'nullable|integer|exists:content_blocks,id',
            'content_blocks.*.type' => 'required|string|in:rich_text,image,gallery,embed,code,separator,heading',
            'content_blocks.*.content' => 'nullable|string',
            'content_blocks.*.media_url' => 'nullable|string|max:255',
            'content_blocks.*.media_alt' => 'nullable|string|max:255',
            'content_blocks.*.settings' => 'nullable|array',
            'content_blocks.*.order' => 'nullable|integer',
        ]);

        $validated = $this->applyPostScheduleStatus($validated);
        $validated = $this->sanitizePostContent($validated);

        $tagIds = $validated['tag_ids'] ?? [];
        $newBlocks = $validated['content_blocks'] ?? [];
        $seoMetadata = $validated['seo_metadata'] ?? null;
        unset($validated['tag_ids'], $validated['content_blocks'], $validated['seo_metadata']);

        if ($seoMetadata) {
            $existingMetadata = $post->metadata ?? [];
            $validated['metadata'] = array_merge($existingMetadata, [
                'seo' => $seoMetadata,
                'seo_generated_at' => now()->toISOString(),
            ]);
        }

        $post->update($validated);

        $post->tags()->sync($tagIds);

        $this->syncContentBlocks($post, $newBlocks);

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post updated successfully.');
    }

    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post deleted successfully.');
    }

    public function bulkAction(BulkActionRequest $request)
    {
        $user = $request->user();
        $action = $request->input('action');
        $ids = $request->input('ids');

        $permission = match ($action) {
            'delete' => 'posts.delete',
            default => 'posts.edit',
        };

        if (! $user->isAdmin() && ! $user->hasPermissionTo($permission)) {
            return redirect()->back()->with('flash.error', 'You are not authorized to update the selected posts.');
        }

        $query = Post::whereIn('id', $ids);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $posts = $query->get();
        $count = $posts->count();

        if ($count === 0) {
            return redirect()->back()->with('flash.error', 'You are not authorized to update the selected posts.');
        }

        match ($action) {
            'publish' => $posts->each(fn (Post $post) => $post->update([
                'status' => 'published',
                'published_at' => now(),
            ])),
            'draft' => $posts->each(fn (Post $post) => $post->update([
                'status' => 'draft',
                'published_at' => null,
            ])),
            'delete' => $posts->each->delete(),
            default => null,
        };

        $label = match ($action) {
            'publish' => 'updated to published',
            'draft' => 'updated to draft',
            'delete' => 'deleted successfully',
            default => 'updated',
        };

        return redirect()->route('dashboard.posts.index')
            ->with('flash.success', "{$count} post(s) {$label}.");
    }

    public function updateStatus(Request $request, Post $post)
    {
        $this->authorize('update', $post);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,published,archived',
        ]);

        $data = $this->applyPostScheduleStatus([
            'status' => $validated['status'],
        ]);

        $post->update($data);

        return redirect()->route('dashboard.posts.index')
            ->with('flash.success', 'Post status updated successfully.');
    }

    protected function applyPostScheduleStatus(array $validated): array
    {
        $status = $validated['status'] ?? 'draft';

        if ($status === 'published') {
            $validated['published_at'] = now();
        } elseif ($status === 'future') {
            $publishDate = $validated['publish_date'] ?? null;

            if ($publishDate) {
                $timestamp = strtotime($publishDate);
                if ($timestamp && $timestamp <= time()) {
                    $validated['status'] = 'published';
                    $validated['published_at'] = now();
                    $validated['publish_date'] = null;
                } else {
                    $validated['published_at'] = $publishDate;
                }
            }
        } else {
            $validated['published_at'] = null;
        }

        return $validated;
    }

    public function uploadImage(Request $request)
    {
        $this->authorize('create', Post::class);

        $service = app(MediaService::class);
        $validated = $request->validate($service->getValidationRules());

        $result = $service->upload($validated['file'], 'posts');

        return response()->json($result);
    }

    public function deleteImage(Request $request)
    {
        $this->authorize('create', Post::class);

        $request->validate(['path' => 'required|string']);

        app(MediaService::class)->delete($request->path);

        return response()->json(['success' => true]);
    }

    protected function syncContentBlocks(Post $post, array $newBlocks): void
    {
        $incomingIds = collect($newBlocks)->pluck('id')->filter();
        $post->contentBlocks()->whereNotIn('id', $incomingIds)->delete();

        foreach ($newBlocks as $order => $block) {
            $block = $this->sanitizeBlock($block);

            if (! empty($block['id'])) {
                ContentBlock::where('id', $block['id'])->where('post_id', $post->id)
                    ->update([
                        'type' => $block['type'],
                        'content' => $block['content'] ?? null,
                        'media_url' => $block['media_url'] ?? null,
                        'media_alt' => $block['media_alt'] ?? null,
                        'settings' => $block['settings'] ?? null,
                        'order' => $block['order'] ?? $order,
                    ]);
            } else {
                $post->contentBlocks()->create([
                    'type' => $block['type'],
                    'content' => $block['content'] ?? null,
                    'media_url' => $block['media_url'] ?? null,
                    'media_alt' => $block['media_alt'] ?? null,
                    'settings' => $block['settings'] ?? null,
                    'order' => $block['order'] ?? $order,
                ]);
            }
        }
    }

    protected function sanitizePostContent(array $validated): array
    {
        $validated['content'] = HtmlSanitizer::sanitize($validated['content'] ?? null);
        $validated['excerpt'] = HtmlSanitizer::sanitizeText($validated['excerpt'] ?? null);
        $validated['featured_image'] = HtmlSanitizer::sanitizeImageUrl($validated['featured_image'] ?? null);
        $validated['og_image'] = HtmlSanitizer::sanitizeImageUrl($validated['og_image'] ?? null);

        return $validated;
    }

    protected function sanitizeBlock(array $block): array
    {
        $type = $block['type'] ?? null;

        $block['content'] = match ($type) {
            'rich_text', 'code', 'heading' => HtmlSanitizer::sanitize($block['content'] ?? null),
            'embed' => HtmlSanitizer::sanitizeUrl($block['content'] ?? null),
            default => $block['content'] ?? null,
        };

        if (isset($block['media_url'])) {
            $block['media_url'] = HtmlSanitizer::sanitizeImageUrl($block['media_url']);
        }

        return $block;
    }

    public function dashboard()
    {
        $this->authorize('viewAny', Post::class);

        return Inertia::render('Blog/Dashboard', [
            'stats' => [
                'total' => Post::count(),
                'published' => Post::where('status', 'published')->count(),
                'draft' => Post::where('status', 'draft')->count(),
                'archived' => Post::where('status', 'archived')->count(),
            ],
            'recentPosts' => Post::with('user', 'category')
                ->latest()
                ->take(5)
                ->get(['id', 'title', 'status', 'category_id', 'user_id', 'created_at']),
        ]);
    }
}
