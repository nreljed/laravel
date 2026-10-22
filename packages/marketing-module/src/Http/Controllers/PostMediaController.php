<?php

namespace Nreljed\MarketingModule\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nreljed\MarketingModule\Models\SocialPost;
use Nreljed\MarketingModule\Support\PostMedia;
use Nreljed\Platform\Http\Controllers\Controller;
use Nreljed\Platform\Services\MediaService;

/**
 * Uploads and deletes the images the composer attaches to a post.
 *
 * Kept separate from MediaController: post media is stored as JPEG under the
 * feature's own folder and scoped to the post permission, whereas the shared
 * media library is profile-agnostic.
 */
class PostMediaController extends Controller
{
    public function upload(Request $request, MediaService $media): JsonResponse
    {
        $this->authorize('create', SocialPost::class);

        $profile = PostMedia::profile((int) $request->user()->id);

        $request->validate($media->rulesFor($profile));

        $result = $media->save($request->file('file'), $profile);

        $media->recordMedium(
            (int) $request->user()->id,
            PostMedia::folder(),
            $result,
        );

        return response()->json($result);
    }

    public function destroy(Request $request, MediaService $media): JsonResponse
    {
        $this->authorize('create', SocialPost::class);

        $request->validate(['path' => 'required|string|max:2048']);

        $path = ltrim(str_replace('\\', '/', $request->string('path')->toString()), '/');

        if (! PostMedia::isManagedPath($path)) {
            abort(403, 'This file does not belong to a post.');
        }

        $media->delete($path);

        return response()->json(['success' => true]);
    }
}
