<?php

namespace App\TestPosts\Controllers;

use App\Http\Controllers\Controller;
use App\Services\MediaService;
use App\TestPosts\Models\TestPost;
use App\TestPosts\Requests\BulkActionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TestPostController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', TestPost::class);

        $testPosts = TestPost::with('user')
            ->latest()
            ->get(['id', 'title', 'slug', 'excerpt', 'status', 'featured_image', 'published_at', 'user_id', 'created_at']);

        return Inertia::render('TestPosts/Index', [
            'testPosts' => $testPosts,
        ]);
    }

    public function create()
    {
        $this->authorize('create', TestPost::class);

        return Inertia::render('TestPosts/Form', [
            'testPost' => null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', TestPost::class);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|string|max:255',
            'gallery_images' => 'nullable|array',
            'gallery_images.*.url' => 'required|string|max:255',
            'gallery_images.*.alt' => 'nullable|string|max:255',
            'gallery_images.*.caption' => 'nullable|string|max:500',
            'gallery_images.*.order' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,published',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|array',
            'map_zoom' => 'nullable|integer|between:1,22',
        ]);

        $validated['user_id'] = $request->user()->id;

        if (($validated['status'] ?? 'draft') === 'published') {
            $validated['published_at'] = now();
        }

        if (empty($validated['featured_image']) && ! empty($validated['gallery_images'])) {
            $validated['featured_image'] = $validated['gallery_images'][0]['url'] ?? null;
        }

        TestPost::create($validated);

        return redirect()->route('dashboard.test-posts.index')
            ->with('status', 'Test post created successfully.');
    }

    public function edit(TestPost $testPost)
    {
        $this->authorize('update', $testPost);

        $testPost->load('user');

        return Inertia::render('TestPosts/Form', [
            'testPost' => $testPost,
        ]);
    }

    public function update(Request $request, TestPost $testPost)
    {
        $this->authorize('update', $testPost);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'excerpt' => 'nullable|string|max:500',
            'featured_image' => 'nullable|string|max:255',
            'gallery_images' => 'nullable|array',
            'gallery_images.*.url' => 'required|string|max:255',
            'gallery_images.*.alt' => 'nullable|string|max:255',
            'gallery_images.*.caption' => 'nullable|string|max:500',
            'gallery_images.*.order' => 'nullable|integer',
            'status' => 'nullable|string|in:draft,published',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|array',
            'map_zoom' => 'nullable|integer|between:1,22',
        ]);

        if (($validated['status'] ?? 'draft') === 'published') {
            $validated['published_at'] = $testPost->published_at ?? now();
        } elseif (($validated['status'] ?? 'draft') === 'draft') {
            $validated['published_at'] = null;
        }

        if (empty($validated['featured_image']) && ! empty($validated['gallery_images'])) {
            $validated['featured_image'] = $validated['gallery_images'][0]['url'] ?? null;
        }

        $testPost->update($validated);

        return redirect()->route('dashboard.test-posts.index')
            ->with('status', 'Test post updated successfully.');
    }

    public function destroy(TestPost $testPost): RedirectResponse
    {
        $this->authorize('delete', $testPost);

        $testPost->delete();

        return redirect()->route('dashboard.test-posts.index')
            ->with('status', 'Test post deleted successfully.');
    }

    public function uploadImage(Request $request)
    {
        $this->authorize('create', TestPost::class);

        $service = app(MediaService::class);
        $validated = $request->validate($service->getValidationRules());

        $result = $service->upload($validated['file'], 'test-posts');

        return response()->json($result);
    }

    public function deleteImage(Request $request)
    {
        $this->authorize('create', TestPost::class);

        $request->validate(['path' => 'required|string']);

        app(MediaService::class)->delete($request->path);

        return response()->json(['success' => true]);
    }

    public function bulkAction(BulkActionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $action = $request->input('action');
        $ids = $request->input('ids');

        $permission = match ($action) {
            'delete' => 'test-posts.delete',
            default => 'test-posts.edit',
        };

        if (! $user->isAdmin() && ! $user->hasPermissionTo($permission)) {
            return redirect()->back()->with('flash.error', 'You are not authorized to update the selected test posts.');
        }

        $query = TestPost::whereIn('id', $ids);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        $testPosts = $query->get();
        $count = $testPosts->count();

        if ($count === 0) {
            return redirect()->back()->with('flash.error', 'You are not authorized to update the selected test posts.');
        }

        match ($action) {
            'publish' => $testPosts->each(fn (TestPost $testPost) => $testPost->update([
                'status' => 'published',
                'published_at' => now(),
            ])),
            'draft' => $testPosts->each(fn (TestPost $testPost) => $testPost->update([
                'status' => 'draft',
                'published_at' => null,
            ])),
            'delete' => $testPosts->each->delete(),
        };

        $label = match ($action) {
            'publish' => 'updated to published',
            'draft' => 'updated to draft',
            'delete' => 'deleted successfully',
        };

        return redirect()->route('dashboard.test-posts.index')
            ->with('flash.success', "{$count} test post(s) {$label}.");
    }

    public function updateStatus(Request $request, TestPost $testPost): RedirectResponse
    {
        $this->authorize('update', $testPost);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,published',
        ]);

        $data = [
            'status' => $validated['status'],
            'published_at' => $validated['status'] === 'published' ? now() : null,
        ];

        $testPost->update($data);

        return redirect()->route('dashboard.test-posts.index')
            ->with('flash.success', 'Test post status updated successfully.');
    }

    public function dashboard()
    {
        $this->authorize('viewAny', TestPost::class);

        return Inertia::render('TestPosts/Dashboard', [
            'stats' => [
                'total' => TestPost::count(),
                'published' => TestPost::where('status', 'published')->count(),
                'draft' => TestPost::where('status', 'draft')->count(),
            ],
            'recentPosts' => TestPost::with('user')
                ->latest()
                ->take(5)
                ->get(['id', 'title', 'status', 'user_id', 'created_at']),
        ]);
    }
}
