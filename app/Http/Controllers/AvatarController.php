<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    public function upload(Request $request)
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:51200'],
        ]);

        $user = $request->user();
        $file = $request->file('avatar');

        $filename = $file->store('avatars');

        if ($user->avatar && $user->avatar !== $filename) {
            Storage::disk('avatars')->delete($user->avatar);
        }

        $user->update(['avatar' => $filename]);
        $user->refresh();

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
