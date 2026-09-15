<?php

namespace Nreljed\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nreljed\Platform\Models\Media;
use Nreljed\Platform\Services\MediaService;
use Nreljed\Platform\Services\UploadProfiles;

class MediaController extends Controller
{
    /**
     * JSON listing for the shared Media Library (grid + picker).
     *
     * Per-user scoping unless the caller holds `media.manage` or `posts.manage`.
     * Supports search, MIME-type prefix filter, folder scope, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $canSeeAll = $this->canManageAny($user);

        $query = Media::query()
            ->when(! $canSeeAll, fn ($query) => $query->where('user_id', $user->id));

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->toString();
            $query->where(function ($query) use ($search) {
                $query->where('filename', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%");
            });
        }

        if ($request->filled('mime_type')) {
            $mime = $request->string('mime_type')->trim()->toString();
            $query->where(fn ($query) => $query->where('mime_type', 'like', "{$mime}%"));
        }

        if ($request->filled('folder')) {
            $folder = $request->string('folder')->trim()->toString();
            if (app(MediaService::class)->isSafeFolder($folder)) {
                $query->where('folder', $folder);
            }
        }

        $perPage = min(max((int) $request->integer('per_page', 24), 1), 60);

        $paginator = $query->latest()->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'totalPages' => $paginator->lastPage(),
            'page' => $paginator->currentPage(),
            'perPage' => $paginator->perPage(),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $service = app(MediaService::class);

        $folder = $request->string('folder', 'media-library')->trim()->toString();
        if (! $service->isSafeFolder($folder)) {
            $folder = 'media-library';
        }

        $profile = UploadProfiles::mediaLibrary($folder, $request->user()->id);

        $request->validate($service->rulesFor($profile));

        $result = $service->save($request->file('file'), $profile);

        $media = $service->recordMedium($request->user()->id, $folder, $result);

        return response()->json([
            'success' => true,
            'data' => $media->load('user'),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['path' => 'required|string']);

        $user = $request->user();

        $media = Media::where('path', $request->path)->first();

        $canManageAny = $this->canManageAny($user);

        if (! $media) {
            app(MediaService::class)->delete($request->path);

            return response()->json(['success' => true]);
        }

        if (! $canManageAny && $media->user_id !== $user->id) {
            abort(403, 'You are not allowed to delete this file.');
        }

        $folder = $request->string('folder', '')->trim()->toString();
        if ($folder !== '' && $media->folder !== $folder) {
            abort(403, 'This file is not in the requested folder.');
        }

        $media->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Reconciliation scan against the media library folder. Reports `media`
     * rows whose file is missing on disk, plus files on disk with no row.
     *
     * Non-managers only see their own dangling records and never granular
     * orphan files (those aren't owned by any user).
     */
    public function scan(Request $request): JsonResponse
    {
        $request->validate([
            'folder' => 'required|string|max:100',
        ]);

        $folder = $request->string('folder')->trim()->toString();

        if (! app(MediaService::class)->isSafeFolder($folder)) {
            abort(422, 'Invalid folder.');
        }

        $user = $request->user();
        $service = app(MediaService::class);

        if ($this->canManageAny($user)) {
            return response()->json($service->scanFolder($folder));
        }

        return response()->json($service->scanFolder($folder, $user->id, false));
    }

    /**
     * Clean up orphans found by scan(). Mode:
     *   - `records` — delete Media rows whose primary file is gone.
     *   - `files`   — delete unrecorded files from disk (managers only).
     *   - `both`    — records + files.
     */
    public function cleanup(Request $request): JsonResponse
    {
        $request->validate([
            'folder' => 'required|string|max:100',
            'mode' => 'required|string|in:records,files,both',
        ]);

        $folder = $request->string('folder')->trim()->toString();
        $mode = $request->string('mode')->trim()->toString();

        $service = app(MediaService::class);

        if (! $service->isSafeFolder($folder)) {
            abort(422, 'Invalid folder.');
        }

        $user = $request->user();
        $canManageAny = $this->canManageAny($user);

        if (in_array($mode, ['files', 'both'], true) && ! $canManageAny) {
            abort(403, 'You are not allowed to delete unrecorded files.');
        }

        $removedRecords = 0;
        $removedFiles = 0;

        if (in_array($mode, ['records', 'both'], true)) {
            $removedRecords = $service->deleteMissingRecords($folder, $canManageAny ? null : $user->id);
        }

        if (in_array($mode, ['files', 'both'], true)) {
            $removedFiles = $service->deleteOrphanFiles($folder);
        }

        return response()->json([
            'success' => true,
            'removed_records' => $removedRecords,
            'removed_files' => $removedFiles,
        ]);
    }

    /**
     * Whether the user can manage any media (see/edit/delete all rows).
     */
    private function canManageAny($user): bool
    {
        return $user->isAdmin()
            || $user->hasPermission('media.manage')
            || $user->hasPermission('posts.manage');
    }
}
