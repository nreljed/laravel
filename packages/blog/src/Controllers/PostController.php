<?php

namespace Nreljed\Blog\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Nreljed\Blog\Models\Post;
use Nreljed\Blog\Models\PostCategory;
use Nreljed\Blog\Models\PostTag;
use Nreljed\Blog\Requests\BulkActionRequest;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Services\HtmlSanitizer;
use Nreljed\Platform\Services\MediaService;
use Nreljed\Platform\Services\UploadProfiles;

class PostController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Post::class);

        $user = $request->user();

        $posts = Post::with('user', 'category', 'tags')
            ->when(! $user->hasPermission('posts.manage'), fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get(['id', 'title', 'slug', 'excerpt', 'status', 'featured_image', 'published_at', 'category_id', 'user_id', 'created_at', 'meta_title', 'metadata'])
            ->each(function (Post $post) {
                $post->setAttribute('seo_score', $post->seoScore());
                $post->setAttribute('seo_status', $post->seoStatus());
                $post->setAttribute('preview_url', $post->status === 'published' ? null : $this->previewUrl($post));
            });

        return Inertia::render('posts/index', [
            'posts' => $posts,
            'categories' => PostCategory::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Post::class);

        return Inertia::render('posts/create', [
            'post' => null,
            'categories' => $this->categoryTree(),
            'tags' => PostTag::orderBy('name')->get(['id', 'name']),
            'preview_url' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Post::class);

        $validated = $request->validate(self::validationRules());

        $validated['user_id'] = $request->user()->id;
        [$validated, $tagIds] = $this->preparePostData($validated);

        $post = Post::create($validated);

        if (! empty($tagIds)) {
            $post->tags()->attach($tagIds);
        }

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post created successfully.');
    }

    public function edit(Post $post): Response
    {
        $this->authorize('update', $post);

        $post->load('tags', 'category');

        return Inertia::render('posts/edit', [
            'post' => $post,
            'categories' => $this->categoryTree(),
            'tags' => PostTag::orderBy('name')->get(['id', 'name']),
            'preview_url' => $post->status !== 'published' ? $this->previewUrl($post) : null,
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);

        $validated = $request->validate(self::validationRules());

        [$validated, $tagIds] = $this->preparePostData($validated, $post->metadata);

        $post->update($validated);
        $post->tags()->sync($tagIds);

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post updated successfully.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return redirect()->route('dashboard.posts.index')
            ->with('status', 'Post deleted successfully.');
    }

    public function bulkAction(BulkActionRequest $request): RedirectResponse
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

        if (! $user->hasPermission('posts.manage')) {
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

    public function updateStatus(Request $request, Post $post): RedirectResponse
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

    public function uploadImage(Request $request): JsonResponse
    {
        $this->authorize('create', Post::class);

        $service = app(MediaService::class);
        $profile = UploadProfiles::posts($request->user()->id);

        $request->validate($service->rulesFor($profile));

        $result = $service->save($request->file('file'), $profile);

        $service->recordMedium($request->user()->id, 'posts', $result);

        return response()->json($result);
    }

    public function deleteImage(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermission('posts.edit')) {
            abort(403);
        }

        $request->validate(['path' => 'required|string']);

        app(MediaService::class)->delete($request->path);

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function validationRules(): array
    {
        return [
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
        ];
    }

    /**
     * Extract tag IDs, merge SEO metadata, schedule status, and sanitize content.
     *
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    protected function preparePostData(array $validated, ?array $existingMetadata = null): array
    {
        $validated = $this->applyPostScheduleStatus($validated);
        $validated = $this->sanitizePostContent($validated);

        $tagIds = $validated['tag_ids'] ?? [];
        $seoMetadata = $validated['seo_metadata'] ?? null;
        unset($validated['tag_ids'], $validated['seo_metadata']);

        $metadata = $existingMetadata ?? $validated['metadata'] ?? [];

        if ($seoMetadata !== null) {
            $metadata['seo'] = $seoMetadata;
            $metadata['seo_generated_at'] = now()->toISOString();
        }

        if ($seoMetadata !== null) {
            $validated['metadata'] = $metadata;
        }

        return [$validated, $tagIds];
    }

    protected function sanitizePostContent(array $validated): array
    {
        $validated['content'] = HtmlSanitizer::sanitize($validated['content'] ?? null);
        $validated['excerpt'] = HtmlSanitizer::sanitizeText($validated['excerpt'] ?? null);
        $validated['featured_image'] = HtmlSanitizer::sanitizeImageUrl($validated['featured_image'] ?? null);
        $validated['og_image'] = HtmlSanitizer::sanitizeImageUrl($validated['og_image'] ?? null);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
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

    protected function previewUrl(Post $post): string
    {
        return URL::signedRoute('blog.preview', ['post' => $post->id], now()->addHours(24));
    }

    public function dashboard(Request $request): Response
    {
        $this->authorize('viewAny', Post::class);

        $user = $request->user();

        $postsQuery = fn () => Post::query()
            ->when(! $user->hasPermission('posts.manage'), fn ($query) => $query->where('user_id', $user->id));

        $start = now()->subMonths(11)->startOfMonth();

        $dailyCounts = $postsQuery()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $monthlyCounts = [];
        foreach ($dailyCounts as $date => $total) {
            $month = substr($date, 0, 7);
            $monthlyCounts[$month] = ($monthlyCounts[$month] ?? 0) + (int) $total;
        }

        $monthlyTrend = collect(range(11, 0))
            ->map(fn (int $offset) => [
                'month' => now()->subMonths($offset)->format('Y-m'),
                'total' => (int) ($monthlyCounts[now()->subMonths($offset)->format('Y-m')] ?? 0),
            ])
            ->values()
            ->all();

        $categoryDistribution = $postsQuery()
            ->join('post_categories', 'post_categories.id', '=', 'posts.category_id')
            ->selectRaw('post_categories.name, COUNT(*) as total')
            ->groupBy('post_categories.id', 'post_categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'value' => (int) $row->total])
            ->values()
            ->all();

        return Inertia::render('posts/dashboard', [
            'stats' => [
                'total' => $postsQuery()->count(),
                'published' => $postsQuery()->where('status', 'published')->count(),
                'draft' => $postsQuery()->where('status', 'draft')->count(),
                'archived' => $postsQuery()->where('status', 'archived')->count(),
            ],
            'monthlyTrend' => $monthlyTrend,
            'categoryDistribution' => $categoryDistribution,
            'recentPosts' => $postsQuery()
                ->with('user', 'category')
                ->latest()
                ->take(5)
                ->get(['id', 'title', 'status', 'category_id', 'user_id', 'created_at']),
        ]);
    }

    public function media(): Response
    {
        $this->authorize('viewAny', Post::class);

        return Inertia::render('posts/media');
    }

    /**
     * Category tree in the Sass/form shape consumed by the SDK TaxonomySelector:
     * parent-less rows are roots, `parent` holds the parent category id, `count`
     * is the number of posts in the category.
     */
    private function categoryTree(): array
    {
        return PostCategory::withCount('posts')
            ->ordered()
            ->get(['id', 'name', 'parent_id'])
            ->map(fn (PostCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'parent' => $category->parent_id,
                'count' => $category->posts_count,
            ])
            ->all();
    }
}
