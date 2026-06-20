<?php

namespace App\Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BookingAjaxController extends Controller
{
    public function uploadDamagePhoto(Request $request): \Illuminate\Http\JsonResponse
    {
        $bookingId = (int) $request->input('post_id', 0);
        if (!$bookingId) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $booking = Booking::find($bookingId);
        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        if (!auth()->user()->can('update', $booking)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $request->validate([
            'file' => 'required|file|image|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('booking_damage/' . $bookingId, 'public');

        $url = Storage::disk('public')->url($path);

        $metadata = $booking->metadata ?? [];
        $gallery = $metadata['damage_photos'] ?? [];
        if (!is_array($gallery)) {
            $gallery = [];
        }
        $gallery[] = [
            'path' => $path,
            'url' => $url,
            'original_name' => $file->getClientOriginalName(),
            'uploaded_at' => now()->toDateTimeString(),
        ];
        $metadata['damage_photos'] = $gallery;
        $booking->update(['metadata' => $metadata]);

        return response()->json([
            'success' => true,
            'id' => count($gallery) - 1,
            'url' => $url,
        ]);
    }

    public function deleteDamagePhoto(Request $request): \Illuminate\Http\JsonResponse
    {
        $bookingId = (int) $request->input('post_id', 0);
        $photoIndex = $request->input('photo_index');

        if (!$bookingId || $photoIndex === null) {
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $booking = Booking::find($bookingId);
        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        if (!auth()->user()->can('update', $booking)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $metadata = $booking->metadata ?? [];
        $gallery = $metadata['damage_photos'] ?? [];
        if (!is_array($gallery) || !isset($gallery[$photoIndex])) {
            return response()->json(['error' => 'Photo not found'], 404);
        }

        $photo = $gallery[$photoIndex];
        if (isset($photo['path'])) {
            Storage::disk('public')->delete($photo['path']);
        }

        unset($gallery[$photoIndex]);
        $metadata['damage_photos'] = array_values($gallery);
        $booking->update(['metadata' => $metadata]);

        return response()->json(['success' => true, 'deleted' => true]);
    }
}
