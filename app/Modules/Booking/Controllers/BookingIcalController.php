<?php

namespace App\Modules\Booking\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Services\BookingIcalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingIcalController extends Controller
{
    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
            'name' => 'nullable|string|max:255',
            'property_id' => 'required|integer|exists:properties,id',
        ]);

        $service = new BookingIcalService();
        $result = $service->importFeed(
            (int) $validated['property_id'],
            $validated['url'],
            $validated['name'] ?? '',
        );

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function feeds(Request $request): JsonResponse
    {
        $propertyId = (int) $request->input('property_id');
        if (!$propertyId) {
            return response()->json(['feeds' => []]);
        }

        $service = new BookingIcalService();
        return response()->json(['feeds' => $service->getFeeds($propertyId)]);
    }

    public function sync(string $feedId, Request $request): JsonResponse
    {
        $propertyId = (int) $request->input('property_id');
        if (!$propertyId) {
            return response()->json(['success' => false, 'error' => 'Property ID required.'], 400);
        }

        $service = new BookingIcalService();
        $result = $service->syncFeed($propertyId, $feedId);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    public function destroy(string $feedId, Request $request): JsonResponse
    {
        $propertyId = (int) $request->input('property_id');
        if (!$propertyId) {
            return response()->json(['success' => false, 'error' => 'Property ID required.'], 400);
        }

        $service = new BookingIcalService();
        $deleted = $service->deleteFeed($propertyId, $feedId);

        if (!$deleted) {
            return response()->json(['success' => false, 'error' => 'Feed not found.'], 404);
        }

        return response()->json(['success' => true]);
    }
}
