<?php

namespace App\Modules\Posts\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Posts\Models\ContentBlock;
use App\Modules\Posts\Models\Post;
use App\Modules\Posts\Models\PostCategory;
use App\Modules\Posts\Models\PostTag;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Post::class);

        $query = Post::with('user', 'category');

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->category_id) {
            $query->where('category_id', $categoryId);
        }

        $posts = $query->latest()->paginate(12)->withQueryString();

        return Inertia::render('Modules/Posts/Index', [
            'posts' => $posts,
            'categories' => PostCategory::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'status', 'category_id']),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Post::class);

        return Inertia::render('Modules/Posts/Form', [
            'post' => null,
            'categories' => PostCategory::orderBy('name')->get(['id', 'name']),
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

        $tagIds = $validated['tag_ids'] ?? [];
        $contentBlocks = $validated['content_blocks'] ?? [];
        unset($validated['tag_ids'], $validated['content_blocks']);

        $post = Post::create($validated);

        if (! empty($tagIds)) {
            $post->tags()->attach($tagIds);
        }

        if (! empty($contentBlocks)) {
            foreach ($contentBlocks as $block) {
                $post->contentBlocks()->create($block);
            }
        }

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post created successfully.');
    }

    public function edit(Post $post)
    {
        $this->authorize('update', $post);

        $post->load('contentBlocks', 'tags', 'category');

        return Inertia::render('Modules/Posts/Form', [
            'post' => $post,
            'categories' => PostCategory::orderBy('name')->get(['id', 'name']),
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

        $tagIds = $validated['tag_ids'] ?? [];
        $newBlocks = $validated['content_blocks'] ?? [];
        unset($validated['tag_ids'], $validated['content_blocks']);

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
        $service = app(MediaService::class);
        $validated = $request->validate($service->getValidationRules());

        $result = $service->upload($validated['file'], 'posts');

        return response()->json($result);
    }

    public function deleteImage(Request $request)
    {
        $request->validate(['path' => 'required|string']);

        app(MediaService::class)->delete($request->path);

        return response()->json(['success' => true]);
    }

    protected function syncContentBlocks(Post $post, array $newBlocks): void
    {
        $incomingIds = collect($newBlocks)->pluck('id')->filter();
        $post->contentBlocks()->whereNotIn('id', $incomingIds)->delete();

        foreach ($newBlocks as $order => $block) {
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

    public function dashboard()
    {
        $this->authorize('viewAny', Post::class);

        return Inertia::render('Modules/Posts/Dashboard', [
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
