<?php

namespace Nreljed\DashboardCore\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Nreljed\DashboardCore\Enums\AuditAction;
use Nreljed\DashboardCore\Services\AuditService;

class AvatarController extends Controller
{
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $file = $request->file('avatar');

        if (! $file || ! $file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Avatar upload failed. Please try again.',
            ], 422);
        }

        // Delete old avatar from the avatars disk before storing new one
        if ($user->avatar) {
            Storage::disk('avatars')->delete($user->avatar);
        }

        // Use file_get_contents + put() instead of store() to avoid
        // Windows realpath() failure in SplFileInfo::getRealPath().
        try {
            $filename = $file->hashName();
            $contents = file_get_contents($file->getPathname());

            if ($contents === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Avatar upload failed. Please try again.',
                ], 500);
            }

            Storage::disk('avatars')->put($filename, $contents);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Avatar storage failed. Please try again.',
            ], 500);
        }

        $user->update(['avatar' => $filename]);
        $user->refresh();

        AuditService::log(AuditAction::ProfileUpdate, $user->id, [
            'changed_fields' => ['avatar'],
        ]);

        $avatarUrl = $user->avatar_url;
        $cacheBust = $avatarUrl ? time() : time();
        $urlWithBuster = $avatarUrl ? $avatarUrl.'?v='.$cacheBust : null;

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully!',
            'avatar' => [
                'url' => $urlWithBuster,
            ],
            'user' => [
                'id' => $user->id,
                'avatar' => $urlWithBuster,
            ],
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('avatars')->delete($user->avatar);
            $user->update(['avatar' => null]);

            AuditService::log(AuditAction::ProfileUpdate, $user->id, [
                'changed_fields' => ['avatar'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar removed successfully!',
            'user' => [
                'id' => $user->id,
                'avatar' => null,
            ],
        ]);
    }
}
